<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Business;
use App\Models\Product;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ========== USERS ==========
        User::create([
            'name'      => 'Super Admin',
            'email'     => 'superadmin@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'superadmin',
            'is_active' => true,
        ]);

        User::create([
            'name'      => 'Admin',
            'email'     => 'admin@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        User::create([
            'name'      => 'Kasir 1',
            'email'     => 'kasir1@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'kasir',
            'is_active' => true,
        ]);

        User::create([
            'name'      => 'Kasir 2',
            'email'     => 'kasir2@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'kasir',
            'is_active' => true,
        ]);

        // ========== BUSINESSES ==========
        $fnb = Business::create([
            'name'        => 'GVI Cafe',
            'description' => 'Bisnis F&B GVI',
            'is_active'   => true,
        ]);

        $retail = Business::create([
            'name'        => 'GVI Retail',
            'description' => 'Bisnis retail & merchandise GVI',
            'is_active'   => true,
        ]);

        $service = Business::create([
            'name'        => 'GVI Service',
            'description' => 'Bisnis jasa & servis GVI',
            'is_active'   => true,
        ]);

        // ========== PRODUCTS - GVI CAFE ==========
        $cafeProducts = [
            ['name' => 'Kopi Americano',      'price' => 25000,  'stock' => 100],
            ['name' => 'Kopi Latte',           'price' => 30000,  'stock' => 100],
            ['name' => 'Cappuccino',           'price' => 30000,  'stock' => 100],
            ['name' => 'Matcha Latte',         'price' => 35000,  'stock' => 80],
            ['name' => 'Es Teh Manis',         'price' => 10000,  'stock' => 150],
            ['name' => 'Jus Alpukat',          'price' => 20000,  'stock' => 50],
            ['name' => 'Mineral Water',        'price' => 8000,   'stock' => 200],
            ['name' => 'Nasi Goreng Spesial',  'price' => 35000,  'stock' => 50],
            ['name' => 'Mie Goreng',           'price' => 30000,  'stock' => 50],
            ['name' => 'Sandwich Keju',        'price' => 28000,  'stock' => 40],
            ['name' => 'Croissant',            'price' => 22000,  'stock' => 30],
            ['name' => 'Cheesecake',           'price' => 32000,  'stock' => 25],
        ];

        foreach ($cafeProducts as $p) {
            Product::create([
                'business_id' => $fnb->id,
                'name'        => $p['name'],
                'price'       => $p['price'],
                'stock'       => $p['stock'],
                'is_active'   => true,
            ]);
        }

        // ========== PRODUCTS - GVI RETAIL ==========
        $retailProducts = [
            ['name' => 'Kaos GVI Logo',        'sku' => 'RTL-001', 'price' => 85000,  'stock' => 50],
            ['name' => 'Topi GVI',             'sku' => 'RTL-002', 'price' => 65000,  'stock' => 40],
            ['name' => 'Totebag GVI',          'sku' => 'RTL-003', 'price' => 55000,  'stock' => 60],
            ['name' => 'Sticker Pack GVI',     'sku' => 'RTL-004', 'price' => 15000,  'stock' => 100],
            ['name' => 'Mug GVI',              'sku' => 'RTL-005', 'price' => 75000,  'stock' => 35],
            ['name' => 'Hoodie GVI',           'sku' => 'RTL-006', 'price' => 185000, 'stock' => 25],
            ['name' => 'Lanyard GVI',          'sku' => 'RTL-007', 'price' => 25000,  'stock' => 80],
            ['name' => 'Notebook GVI',         'sku' => 'RTL-008', 'price' => 45000,  'stock' => 45],
        ];

        foreach ($retailProducts as $p) {
            Product::create([
                'business_id' => $retail->id,
                'name'        => $p['name'],
                'sku'         => $p['sku'],
                'price'       => $p['price'],
                'stock'       => $p['stock'],
                'is_active'   => true,
            ]);
        }

        // ========== PRODUCTS - GVI SERVICE ==========
        $serviceProducts = [
            ['name' => 'Instalasi Digital Signage',    'sku' => 'SVC-001', 'price' => 500000,  'stock' => 999],
            ['name' => 'Servis & Maintenance LCD',     'sku' => 'SVC-002', 'price' => 350000,  'stock' => 999],
            ['name' => 'Konfigurasi LED Controller',   'sku' => 'SVC-003', 'price' => 250000,  'stock' => 999],
            ['name' => 'Training Penggunaan Sistem',   'sku' => 'SVC-004', 'price' => 750000,  'stock' => 999],
            ['name' => 'Konsultasi Teknis (per jam)',  'sku' => 'SVC-005', 'price' => 200000,  'stock' => 999],
        ];

        foreach ($serviceProducts as $p) {
            Product::create([
                'business_id' => $service->id,
                'name'        => $p['name'],
                'sku'         => $p['sku'],
                'price'       => $p['price'],
                'stock'       => $p['stock'],
                'is_active'   => true,
            ]);
        }

        echo "✅ Seeder POS berhasil!\n";
        echo "- Users: "     . User::count()     . "\n";
        echo "- Businesses: ". Business::count() . "\n";
        echo "- Products: "  . Product::count()  . "\n";
    }
}
