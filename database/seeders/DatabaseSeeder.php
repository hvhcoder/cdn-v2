<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        User::where('email', 'admin@cdnpro.test')->firstOr(fn () => User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@cdnpro.test',
            'password' => 'password',
        ]));
    }
}
