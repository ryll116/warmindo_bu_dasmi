<?php

namespace Database\Seeders;

use App\Models\Resto;
use Illuminate\Database\Seeder;

class RestoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            Resto::factory()->count(3)->create();
        }
    }
}
