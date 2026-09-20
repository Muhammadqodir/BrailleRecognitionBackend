<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Carries the still-active slice of the legacy `braille` database into this app.
 *
 * Of ~15.5k legacy accounts, roughly 1,164 translated anything in the last year
 * and about 8,100 never translated at all. Importing everything would preserve
 * mostly dead rows, so this takes an activity window and leaves the rest behind
 * in the legacy database, which stays readable as an archive.
 *
 * Two legacy data problems this has to survive:
 *
 *   * `users.email` has no unique index there and this app's does, so duplicates
 *     and empty strings both exist and both would break the insert. The email is
 *     dropped from the losing row rather than the row itself — those accounts
 *     still have history and a `legacy_token`, which is what actually signs them
 *     back in.
 *   * `users.password` is stored in plain text. It is hashed on the way in, so
 *     the cleartext never lands in this database.
 *
 * Writes are chunked upserts keyed on `legacy_id`, in per-chunk transactions.
 * An earlier version did one `updateOrInsert` per row inside a single
 * transaction spanning the whole import: ~7,600 round trips that took over ten
 * minutes and lost everything when the connection dropped near the end. Chunking
 * cuts it to roughly twenty queries and makes a failure resumable — re-running
 * picks up where it stopped instead of starting over.
 *
 * Dry by default — pass --commit to actually write.
 */
class ImportLegacyData extends Command
{
    protected $signature = 'braille:import-legacy
        {--days=365 : Import accounts that translated within this many days}
        {--commit : Actually write. Without this the command only reports.}';

    protected $description = 'Import active accounts and their history from the legacy braille database';

    /** Rows per upsert. Small enough to stay well inside max_allowed_packet. */
    private const CHUNK = 200;

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $commit = (bool) $this->option('commit');

        if ($days < 1) {
            $this->error('--days must be at least 1.');
            return self::FAILURE;
        }

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot reach the legacy database: ' . $e->getMessage());
            $this->line('Set LEGACY_DB_DATABASE / LEGACY_DB_USERNAME / LEGACY_DB_PASSWORD in .env.');
            return self::FAILURE;
        }

        $this->info($commit ? "Importing (COMMIT) — {$days} day window" : "Dry run — {$days} day window. Nothing will be written.");
        $this->newLine();

        $cutoff = now()->subDays($days)->toDateTimeString();

        // The active slice: legacy users with at least one translation in the
        // window. Activity is measured on translations, not the signup date —
        // plenty of accounts registered and never came back.
        $activeIds = DB::connection('legacy')
            ->table('translations')
            ->where('date', '>=', $cutoff)
            ->distinct()
            ->pluck('user_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($activeIds->isEmpty()) {
            $this->warn('No active accounts found in that window. Nothing to do.');
            return self::SUCCESS;
        }

        $users = DB::connection('legacy')
            ->table('users')
            ->whereIn('id', $activeIds)
            ->orderBy('id')
            ->get();

        // Real last-seen, from history rather than the signup date. Pulled in
        // one grouped query so it can be folded into the user rows below
        // instead of costing an UPDATE per account afterwards.
        $lastSeen = DB::connection('legacy')
            ->table('translations')
            ->select('user_id', DB::raw('MAX(`date`) as seen'))
            ->whereIn('user_id', $activeIds)
            ->groupBy('user_id')
            ->pluck('seen', 'user_id');

        // Resolve email collisions before touching the target table. Legacy has
        // no unique index on email, so the same address can appear many times.
        // Last writer by legacy id keeps the address; everyone else imports
        // without one.
        $emailOwner = [];
        foreach ($users as $u) {
            $email = strtolower(trim((string) $u->email));
            if ($email === '' || !str_contains($email, '@')) {
                continue;
            }
            $emailOwner[$email] = $u->id;
        }

        $blankEmails = $users->filter(
            fn ($u) => trim((string) $u->email) === '' || !str_contains((string) $u->email, '@')
        )->count();

        $dupesDropped = $users->filter(function ($u) use ($emailOwner) {
            $email = strtolower(trim((string) $u->email));
            return $email !== '' && isset($emailOwner[$email]) && $emailOwner[$email] !== $u->id;
        })->count();

        $translationCount = DB::connection('legacy')
            ->table('translations')
            ->whereIn('user_id', $activeIds)
            ->count();

        $this->line("Active accounts in window: <info>{$activeIds->count()}</info>");
        $this->line("Legacy user rows found:    <info>{$users->count()}</info>");
        $this->line("Rows with no usable email: <comment>{$blankEmails}</comment>");
        $this->line("Duplicate emails dropped:  <comment>{$dupesDropped}</comment>");
        $this->line("Translations to import:    <info>{$translationCount}</info>");
        $this->newLine();

        if (!$commit) {
            $this->comment('Dry run complete. Re-run with --commit to write.');
            return self::SUCCESS;
        }

        // ---- users -------------------------------------------------------
        $this->line('Hashing passwords and building rows...');
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $rows = [];
        $now = now()->toDateTimeString();

        foreach ($users as $u) {
            $email = strtolower(trim((string) $u->email));
            $keepEmail = $email !== ''
                && str_contains($email, '@')
                && ($emailOwner[$email] ?? null) === $u->id;

            $name = trim((string) $u->name);
            $role = trim((string) $u->role);
            $avatar = trim((string) $u->photo_url);
            $rc = trim((string) ($u->rc_user_id ?? ''));
            $password = (string) $u->password;

            $rows[] = [
                'legacy_id' => (int) $u->id,
                'name' => $name !== '' && $name !== 'undefined' ? mb_substr($name, 0, 255) : null,
                'email' => $keepEmail ? mb_substr($email, 0, 255) : null,
                // Hashed here — the legacy column is cleartext and that must
                // not survive the move. This is the slow part of the import;
                // bcrypt is deliberately expensive.
                'password' => $password !== '' ? Hash::make($password) : null,
                'role' => $role !== '' && $role !== 'undefined' ? mb_substr($role, 0, 255) : null,
                'avatar' => $avatar !== '' && $avatar !== 'undefined' ? mb_substr($avatar, 0, 255) : null,
                'auth_provider' => $this->mapProvider((string) ($u->auth_method ?? '')),
                'rc_user_id' => $rc !== '' ? mb_substr($rc, 0, 64) : null,
                'legacy_token' => mb_substr(trim((string) $u->token), 0, 64) ?: null,
                'last_seen_at' => $lastSeen[$u->id] ?? null,
                'created_at' => $u->date ?: $now,
                'updated_at' => $now,
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $importedUsers = 0;
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::transaction(function () use ($chunk, &$importedUsers) {
                DB::table('users')->upsert(
                    $chunk,
                    ['legacy_id'],
                    // `password` is deliberately absent: it is set on insert and
                    // never rewritten, so re-running does not re-hash 1,164
                    // passwords for no reason.
                    ['name', 'email', 'role', 'avatar', 'auth_provider',
                     'rc_user_id', 'legacy_token', 'last_seen_at', 'updated_at']
                );
                $importedUsers += count($chunk);
            });
            $this->line("  users: {$importedUsers}/" . count($rows));
        }

        // ---- translations ------------------------------------------------
        $idMap = DB::table('users')
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id');

        $importedTranslations = 0;

        DB::connection('legacy')
            ->table('translations')
            ->whereIn('user_id', $activeIds)
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($legacyRows) use ($idMap, &$importedTranslations, $now) {
                $batch = [];
                foreach ($legacyRows as $t) {
                    if (!isset($idMap[$t->user_id])) {
                        continue;
                    }
                    $batch[] = [
                        'legacy_id' => (int) $t->id,
                        'user_id' => $idMap[$t->user_id],
                        'result_braille' => $t->result_braille ?: null,
                        'result' => $t->result ?: null,
                        'result_json' => $t->result_json ?: null,
                        'input_file' => $t->input_file ? mb_substr($t->input_file, 0, 255) : null,
                        'result_marked' => $t->result_marked ? mb_substr($t->result_marked, 0, 255) : null,
                        'lang' => mb_substr((string) ($t->lang ?: 'EN'), 0, 8),
                        'is_fav' => (int) $t->is_fav === 1,
                        'created_at' => $t->date ?: $now,
                        'updated_at' => $now,
                    ];
                }

                if ($batch === []) {
                    return;
                }

                DB::transaction(function () use ($batch, &$importedTranslations) {
                    DB::table('translations')->upsert(
                        $batch,
                        ['legacy_id'],
                        ['user_id', 'result_braille', 'result', 'result_json',
                         'input_file', 'result_marked', 'lang', 'is_fav', 'updated_at']
                    );
                    $importedTranslations += count($batch);
                });
                $this->line("  translations: {$importedTranslations}");
            });

        $this->newLine();
        $this->info("Imported {$importedUsers} accounts and {$importedTranslations} translations.");
        $this->comment('The legacy database was not modified.');

        return self::SUCCESS;
    }

    /** Legacy `auth_method` is inconsistent; normalise to what this app uses. */
    private function mapProvider(string $method): string
    {
        return match (strtolower(trim($method))) {
            'google' => 'google',
            'apple' => 'apple',
            default => 'email',
        };
    }
}
