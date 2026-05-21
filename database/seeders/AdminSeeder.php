<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test admin with known credentials
        Admin::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@petconnect.com',
            'password' => bcrypt('password'),
        ]);

        // Create additional admin users
        Admin::factory(2)->create();
    }
}
