<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CsaSeeder::class);

        // Module seeders (database/seeders/Modules/*Seeder.php) run after the core data
        foreach (glob(__DIR__.'/Modules/*Seeder.php') as $file) {
            $this->call('Database\\Seeders\\Modules\\'.basename($file, '.php'));
        }
    }
}
