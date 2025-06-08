<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        DB::table('users')->insert([
            ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => Hash::make('password123')],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => Hash::make('password456')],
            ['name' => 'Charlie', 'email' => 'charlie@example.com', 'password' => Hash::make('password789')],
        ]);
    }
}
