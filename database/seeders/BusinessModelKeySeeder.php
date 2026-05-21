<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessModelKeySeeder extends Seeder
{
    public function run(): void
    {
        $updates = [
            ['slug' => 'ayam-maya', 'model_key' => 'ayam_maya_category'],
        ];

        foreach ($updates as $data) {
            DB::table('businesses')
                ->where('slug', $data['slug'])
                ->update(['model_key' => $data['model_key']]);

            $this->command->info("Updated: {$data['slug']} → {$data['model_key']}");
        }

        $this->command->info('Done.');
    }
}
