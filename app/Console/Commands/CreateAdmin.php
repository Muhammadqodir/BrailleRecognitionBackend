<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Admins are created from the shell only: the dashboard has no sign-up, so
 * nobody can make themselves an account from the web.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=Admin}';

    protected $description = 'Create a dashboard admin, or reset the password of an existing one';

    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not an email address.');

            return self::FAILURE;
        }

        $typed = $this->secret('Password (leave empty to generate one)');
        $password = $typed ?: Str::password(20, symbols: false);

        $admin = Admin::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name'), 'password' => $password],
        );

        $this->info(($admin->wasRecentlyCreated ? 'Created' : 'Updated')." {$email}.");
        if (! $typed) {
            $this->line("Password: {$password}");
        }
        $this->line('Sign in at '.url(config('braille.admin_path').'/login'));

        return self::SUCCESS;
    }
}
