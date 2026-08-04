<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BusinessTemplateSeeder::class,
            PlanSeeder::class,
            EngineSeeder::class,
        ]);

        if ($this->command?->confirm(
            'Apakah data contoh akun usaha beserta data operasional akan dibuat?',
            app()->environment('local'),
        )) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
