<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Makanan',  'color' => '#F59E0B', 'icon' => '🍔'],
            ['name' => 'Minuman',  'color' => '#06B6D4', 'icon' => '🥤'],
            ['name' => 'Snack',    'color' => '#8B5CF6', 'icon' => '🍿'],
            ['name' => 'Lainnya',  'color' => '#6B7280', 'icon' => '📦'],
        ];

        foreach ($categories as $cat) {
            \App\Models\Category::firstOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
