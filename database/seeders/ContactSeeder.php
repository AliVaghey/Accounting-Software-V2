<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            foreach (['Sara Ahmadi', 'Reza Karimi', 'Nima Rahimi'] as $name) {
                $user->contacts()->firstOrCreate(['name' => $name]);
            }
        });
    }
}
