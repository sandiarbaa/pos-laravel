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
        // ========== SUPERADMIN ==========
        $superadmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@gvipos.com',
            'password' => Hash::make('password'),
            'role'     => 'superadmin',
        ]);

        // ========== ADMIN (Owner Bisnis) ==========
        $adminCafe = User::create([
            'name'     => 'Owner GVI Cafe',
            'email'    => 'admin.cafe@gvipos.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'is_active' => true,
        ]);

        $adminRetail = User::create([
            'name'     => 'Owner GVI Retail',
            'email'    => 'admin.retail@gvipos.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'is_active' => true,
        ]);

        $adminService = User::create([
            'name'     => 'Owner GVI Service',
            'email'    => 'admin.service@gvipos.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'is_active' => true,
        ]);

        // ========== BUSINESSES — owner_id sesuai admin ==========
        $cafe = Business::create([
            'name'        => 'GVI Cafe',
            'description' => 'Cafe & minuman GVI',
            'owner_id'    => $adminCafe->id,
        ]);

        $retail = Business::create([
            'name'        => 'GVI Retail',
            'description' => 'Toko retail GVI',
            'owner_id'    => $adminRetail->id,
        ]);

        $service = Business::create([
            'name'        => 'GVI Service',
            'description' => 'Layanan & inventory GVI',
            'owner_id'    => $adminService->id,
        ]);

        // ========== KASIR — owner_id ke admin masing2 ==========
        User::create([
            'name'        => 'Kasir Cafe 1',
            'email'       => 'kasir1.cafe@gvipos.com',
            'password'    => Hash::make('password'),
            'role'        => 'kasir',
            'is_active'   => true,
            'business_id' => $cafe->id,
            'owner_id'    => $adminCafe->id,
        ]);

        User::create([
            'name'        => 'Kasir Retail 1',
            'email'       => 'kasir1.retail@gvipos.com',
            'password'    => Hash::make('password'),
            'role'        => 'kasir',
            'is_active'   => true,
            'business_id' => $retail->id,
            'owner_id'    => $adminRetail->id,
        ]);

        User::create([
            'name'        => 'Kasir Service 1',
            'email'       => 'kasir1.service@gvipos.com',
            'password'    => Hash::make('password'),
            'role'        => 'kasir',
            'is_active'   => true,
            'business_id' => $service->id,
            'owner_id'    => $adminService->id,
        ]);

        // ========== PRODUK CAFE ==========
        $cafeProducts = [
            ['name' => 'Kopi Americano',  'price' => 25000, 'stock' => 50],
            ['name' => 'Kopi Latte',      'price' => 30000, 'stock' => 50],
            ['name' => 'Cappuccino',      'price' => 32000, 'stock' => 50],
            ['name' => 'Es Teh Manis',    'price' => 10000, 'stock' => 100],
            ['name' => 'Jus Alpukat',     'price' => 20000, 'stock' => 30],
            ['name' => 'Croissant',       'price' => 22000, 'stock' => 20, 'discount_percent' => 10],
            ['name' => 'Cheesecake',      'price' => 35000, 'stock' => 15],
            ['name' => 'Nasi Goreng',     'price' => 28000, 'stock' => 30],
            ['name' => 'Pasta Carbonara', 'price' => 45000, 'stock' => 20, 'discount_percent' => 5],
        ];
        foreach ($cafeProducts as $p) {
            Product::create(array_merge($p, ['business_id' => $cafe->id]));
        }

        // ========== PRODUK RETAIL ==========
        $retailProducts = [
            ['name' => 'Kaos GVI Logo',   'price' => 85000,  'stock' => 30],
            ['name' => 'Tote Bag GVI',    'price' => 65000,  'stock' => 25, 'discount_percent' => 15],
            ['name' => 'Mug GVI',         'price' => 75000,  'stock' => 20],
            ['name' => 'Stiker Pack GVI', 'price' => 15000,  'stock' => 100],
            ['name' => 'Hoodie GVI',      'price' => 195000, 'stock' => 15, 'discount_percent' => 10],
            ['name' => 'Topi GVI',        'price' => 95000,  'stock' => 20],
            ['name' => 'Payung GVI',      'price' => 120000, 'stock' => 10],
            ['name' => 'Notebook GVI',    'price' => 45000,  'stock' => 40],
        ];
        foreach ($retailProducts as $p) {
            Product::create(array_merge($p, ['business_id' => $retail->id]));
        }

        // ========== PRODUK SERVICE ==========
        $serviceProducts = [
            ['name' => 'Instalasi Digital Signage',  'price' => 500000,  'stock' => 999],
            ['name' => 'Konfigurasi LED Controller', 'price' => 350000,  'stock' => 999],
            ['name' => 'Maintenance Tahunan',        'price' => 1200000, 'stock' => 999],
            ['name' => 'Training Operator',          'price' => 750000,  'stock' => 999],
            ['name' => 'Konsultasi Teknis',          'price' => 250000,  'stock' => 999, 'discount_percent' => 20],
            ['name' => 'Penggantian Modul LED',      'price' => 450000,  'stock' => 50],
            ['name' => 'Kabel HDMI 5m',              'price' => 85000,   'stock' => 30],
            ['name' => 'Power Supply LED',           'price' => 320000,  'stock' => 20],
        ];
        foreach ($serviceProducts as $p) {
            Product::create(array_merge($p, ['business_id' => $service->id]));
        }

        echo "✅ Seeder berhasil!\n";
        echo "superadmin@gvipos.com / password\n";
        echo "admin.cafe@gvipos.com / password\n";
        echo "admin.retail@gvipos.com / password\n";
        echo "admin.service@gvipos.com / password\n";
        echo "kasir1.cafe@gvipos.com / password\n";
        echo "kasir1.retail@gvipos.com / password\n";
        echo "kasir1.service@gvipos.com / password\n";
    }
}
