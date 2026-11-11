<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            foreach (['Groceries' => '#a5d6c0', 'Transport' => '#a8c7fa', 'Bills' => '#f1c48e'] as $name => $color) {
                $user->tags()->firstOrCreate(['name' => $name], ['color' => $color]);
            }
        });
    }
}
