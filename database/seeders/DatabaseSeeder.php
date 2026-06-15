<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $admin = User::query()->firstOrNew(['email' => 'info@spareparts.com']);
        $admin->forceFill([
            'name' => 'Sea Requests Admin',
            'company_name' => 'Sea Requests',
            'phone' => null,
            'locale' => 'en',
            'role' => 'admin',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('123456789+3'),
        ])->save();
    }
}
