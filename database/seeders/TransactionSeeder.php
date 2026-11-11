<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            $user->transactions()->firstOrCreate(
                ['description' => 'Sample expense'],
                ['amount' => 100000, 'is_income' => false],
            );
        });
    }
}
