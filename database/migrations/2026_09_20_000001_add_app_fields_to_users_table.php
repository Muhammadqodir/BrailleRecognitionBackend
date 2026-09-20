<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `users` able to hold an account that nobody has signed up for.
 *
 * The app mints an anonymous account on first launch so that someone can scan,
 * hit the paywall and subscribe without ever seeing a registration form.
 * Signing in with Apple or Google is an upgrade of that row, not a new one, so
 * a purchase made anonymously survives the sign-in.
 *
 * That means `name` and `email` can no longer be required. `email` keeps its
 * unique index — MySQL permits any number of NULLs in one, which is exactly
 * the behaviour we want here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Anonymous accounts have neither until the user signs in.
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();

            // The device identity, minted client-side before any account
            // exists. Not unique: a reinstall mints a new one, and one person
            // on two devices legitimately produces two.
            $table->string('install_id', 64)->nullable()->after('id');
            $table->index('install_id');

            // RevenueCat's app user id. Unique because an entitlement must map
            // to exactly one account, and carried across an anonymous -> social
            // sign-in merge so the store never has to alias two identities.
            $table->string('rc_user_id', 64)->nullable()->unique()->after('install_id');

            // Answers to the two onboarding questions. They personalise the
            // paywall copy and are the segment dimension worth reporting
            // conversion against.
            $table->string('role')->nullable()->after('auth_provider');
            $table->string('use_case')->nullable()->after('role');

            // Import bridge from the legacy `braille` database. `legacy_token`
            // is what an already-installed client still sends: we recognise it
            // once, issue a Sanctum token, and the user keeps their history
            // without ever seeing a login screen.
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('use_case');
            $table->string('legacy_token', 64)->nullable()->after('legacy_id');
            $table->index('legacy_token');

            $table->timestamp('last_seen_at')->nullable()->after('legacy_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['install_id']);
            $table->dropIndex(['legacy_token']);
            $table->dropUnique(['rc_user_id']);
            $table->dropUnique(['legacy_id']);
            $table->dropColumn([
                'install_id',
                'rc_user_id',
                'role',
                'use_case',
                'legacy_id',
                'legacy_token',
                'last_seen_at',
            ]);

            // Deliberately not restored to NOT NULL: rows added since this
            // migration ran may legitimately hold nulls, and failing a
            // rollback on them would be worse than leaving the column wider.
        });
    }
};
