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
        ]);

        if ($this->command?->confirm(
            'Seed demo tenant beserta data operasional?',
            app()->environment('local'),
        )) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
