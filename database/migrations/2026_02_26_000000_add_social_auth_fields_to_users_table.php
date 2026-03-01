<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('apple_id')->nullable()->unique()->after('google_id');
            $table->string('avatar')->nullable()->after('apple_id');
            $table->string('auth_provider')->nullable()->after('avatar'); // 'email', 'google', 'apple'
            $table->string('password')->nullable()->change(); // allow null for social-only accounts
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'apple_id', 'avatar', 'auth_provider']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
