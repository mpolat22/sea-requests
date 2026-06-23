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

        $adminEmail = 'admin@searequests.ai';

        $admin = User::query()
            ->where('role', 'admin')
            ->whereIn('email', [
                $adminEmail,
                'info@searequests.com',
                'info@spareparts.com',
            ])
            ->orderByRaw(
                "case
                    when email = ? then 0
                    when email = 'info@searequests.com' then 1
                    when email = 'info@spareparts.com' then 2
                    else 3
                end",
                [$adminEmail]
            )
            ->first() ?? User::query()->firstOrNew(['email' => $adminEmail]);
        $admin->forceFill([
            'name' => 'Sea Requests Admin',
            'email' => $adminEmail,
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
