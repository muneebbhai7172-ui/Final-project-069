<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $email = 'forlaptop7172@gmail.com';
        $existingUser = DB::table('users')->where('email', $email)->first();

        if ($existingUser) {
            DB::table('users')->where('email', $email)->update([
                'role' => 'admin',
                'active' => 1,
                'account_locked' => 0,
                'failed_attempts' => 0,
                'updated_at' => now(),
            ]);

            return;
        }

        $baseUsername = 'forlaptop7172';
        $username = $baseUsername;
        $counter = 1;

        while (DB::table('users')->where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        DB::table('users')->insert([
            'username' => $username,
            'password' => Hash::make('Master@12345'),
            'email' => $email,
            'first_name' => 'Master',
            'last_name' => 'Admin',
            'phone' => null,
            'role' => 'admin',
            'active' => 1,
            'account_locked' => 0,
            'failed_attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->where('email', 'forlaptop7172@gmail.com')
            ->where('first_name', 'Master')
            ->where('last_name', 'Admin')
            ->delete();
    }
};
