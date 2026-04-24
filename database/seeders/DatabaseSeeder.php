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
        User::create([
            'name'      => 'Super Admin',
            'email'     => 'superadmin@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'superadmin',
            'is_active' => true,
        ]);

        // ========== DEMO OWNERS (dari seeder lama) ==========
        $adminCafe = User::create([
            'name'      => 'Owner GVI Cafe',
            'email'     => 'admin.cafe@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $adminRetail = User::create([
            'name'      => 'Owner GVI Retail',
            'email'     => 'admin.retail@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $adminService = User::create([
            'name'      => 'Owner GVI Service',
            'email'     => 'admin.service@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        // ========== OWNER AYAM MAYA ==========
        $ownerAyamMaya = User::create([
            'name'      => 'Owner Ayam Maya',
            'email'     => 'owner.ayammaya@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        // ========== OWNER IKJ KEDAI JUS ==========
        $ownerIkj = User::create([
            'name'      => 'Owner IKJ Kedai Jus',
            'email'     => 'owner.ikj@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        // ========== BUSINESSES ==========
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

        $bizAyamMaya = Business::create([
            'name'        => 'Ayam Maya',
            'description' => 'Resto ayam & bebek — @ayammayaofficial',
            'owner_id'    => $ownerAyamMaya->id,
        ]);

        $bizIkj = Business::create([
            'name'        => 'IKJ - Ini Kedai Jus',
            'description' => 'Kedai jus & camilan sehat — #empatsehatlimaJUS',
            'owner_id'    => $ownerIkj->id,
        ]);

        // ========== KASIR (demo lama) ==========
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

        // Kasir Ayam Maya
        User::create([
            'name'        => 'Kasir Ayam Maya',
            'email'       => 'kasir.ayammaya@gvipos.com',
            'password'    => Hash::make('password'),
            'role'        => 'kasir',
            'is_active'   => true,
            'business_id' => $bizAyamMaya->id,
            'owner_id'    => $ownerAyamMaya->id,
        ]);

        // Kasir IKJ
        User::create([
            'name'        => 'Kasir IKJ',
            'email'       => 'kasir.ikj@gvipos.com',
            'password'    => Hash::make('password'),
            'role'        => 'kasir',
            'is_active'   => true,
            'business_id' => $bizIkj->id,
            'owner_id'    => $ownerIkj->id,
        ]);

        // ========== PRODUK GVI CAFE ==========
        foreach ([
            ['name' => 'Kopi Americano',  'price' => 25000, 'stock' => 50],
            ['name' => 'Kopi Latte',      'price' => 30000, 'stock' => 50],
            ['name' => 'Cappuccino',      'price' => 32000, 'stock' => 50],
            ['name' => 'Es Teh Manis',    'price' => 10000, 'stock' => 100],
            ['name' => 'Jus Alpukat',     'price' => 20000, 'stock' => 30],
            ['name' => 'Croissant',       'price' => 22000, 'stock' => 20, 'discount_percent' => 10],
            ['name' => 'Cheesecake',      'price' => 35000, 'stock' => 15],
            ['name' => 'Nasi Goreng',     'price' => 28000, 'stock' => 30],
            ['name' => 'Pasta Carbonara', 'price' => 45000, 'stock' => 20, 'discount_percent' => 5],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $cafe->id]));
        }

        // ========== PRODUK GVI RETAIL ==========
        foreach ([
            ['name' => 'Kaos GVI Logo',   'price' => 85000,  'stock' => 30],
            ['name' => 'Tote Bag GVI',    'price' => 65000,  'stock' => 25, 'discount_percent' => 15],
            ['name' => 'Mug GVI',         'price' => 75000,  'stock' => 20],
            ['name' => 'Stiker Pack GVI', 'price' => 15000,  'stock' => 100],
            ['name' => 'Hoodie GVI',      'price' => 195000, 'stock' => 15, 'discount_percent' => 10],
            ['name' => 'Topi GVI',        'price' => 95000,  'stock' => 20],
            ['name' => 'Payung GVI',      'price' => 120000, 'stock' => 10],
            ['name' => 'Notebook GVI',    'price' => 45000,  'stock' => 40],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $retail->id]));
        }

        // ========== PRODUK GVI SERVICE ==========
        foreach ([
            ['name' => 'Instalasi Digital Signage',  'price' => 500000,  'stock' => 999],
            ['name' => 'Konfigurasi LED Controller', 'price' => 350000,  'stock' => 999],
            ['name' => 'Maintenance Tahunan',        'price' => 1200000, 'stock' => 999],
            ['name' => 'Training Operator',          'price' => 750000,  'stock' => 999],
            ['name' => 'Konsultasi Teknis',          'price' => 250000,  'stock' => 999, 'discount_percent' => 20],
            ['name' => 'Penggantian Modul LED',      'price' => 450000,  'stock' => 50],
            ['name' => 'Kabel HDMI 5m',              'price' => 85000,   'stock' => 30],
            ['name' => 'Power Supply LED',           'price' => 320000,  'stock' => 20],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $service->id]));
        }

        // ========== PRODUK AYAM MAYA ==========
        // Harga dalam ribuan rupiah sesuai menu (dikali 1000)

        // Minuman
        foreach ([
            ['name' => 'Es Coklat Cao',        'price' => 9000,  'stock' => 999],
            ['name' => 'Es Coklat Cao Jumbo',  'price' => 15000, 'stock' => 999],
            ['name' => 'Jeruk Es/Hangat',      'price' => 8000,  'stock' => 999],
            ['name' => 'Jeruk Jumbo Es/Hangat','price' => 13000, 'stock' => 999],
            ['name' => 'Teh Es/Hangat',        'price' => 4000,  'stock' => 999],
            ['name' => 'Teh Jumbo Es/Hangat',  'price' => 6000,  'stock' => 999],
            ['name' => 'Lemon Tea Es/Hangat',  'price' => 10000, 'stock' => 999],
            ['name' => 'Es Beras Kencur/Sinom','price' => 9000,  'stock' => 999],
            ['name' => 'Air Mineral',          'price' => 5000,  'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Menu Godaan
        foreach ([
            ['name' => 'Ayam Maya',           'price' => 20000, 'stock' => 999],
            ['name' => 'Ayam Kremes',         'price' => 28000, 'stock' => 999],
            ['name' => 'Ayam Goreng',         'price' => 26000, 'stock' => 999],
            ['name' => 'Ayam Rica',           'price' => 28000, 'stock' => 999],
            ['name' => 'Ceker Rica',          'price' => 12000, 'stock' => 999],
            ['name' => 'Bebek Goreng',        'price' => 31000, 'stock' => 999],
            ['name' => 'Iga Goreng',          'price' => 34000, 'stock' => 999],
            ['name' => 'Ayam Hore Bakar',     'price' => 23000, 'stock' => 999],
            ['name' => 'Ayam Kampung Bakar',  'price' => 29000, 'stock' => 999],
            ['name' => 'Bebek Bakar',         'price' => 34000, 'stock' => 999],
            ['name' => 'Iga Bakar',           'price' => 37000, 'stock' => 999],
            ['name' => 'Lele Goreng',         'price' => 14000, 'stock' => 999],
            ['name' => 'Kepala Bebek (2pcs)', 'price' => 10000, 'stock' => 999],
            ['name' => 'Kepala Ayam (2pcs)',  'price' => 10000, 'stock' => 999],
            ['name' => 'Ampela (2pcs)',       'price' => 10000, 'stock' => 999],
            ['name' => 'Ampela Rica (2pcs)',  'price' => 12000, 'stock' => 999],
            ['name' => 'Ayam Pok Pok',       'price' => 15000, 'stock' => 999],
            ['name' => 'Chicken Egg Roll',   'price' => 15000, 'stock' => 999],
            ['name' => 'Telor Dadar/Ceplok', 'price' => 5000,  'stock' => 999],
            ['name' => 'Tempe/Tahu (2pcs)',  'price' => 5000,  'stock' => 999],
            ['name' => 'Nasi Putih',         'price' => 5000,  'stock' => 999],
            ['name' => 'Nasi Uduk',          'price' => 7000,  'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Menu Paket
        foreach ([
            ['name' => 'Paket Nasi Uduk Ayam Hore',       'price' => 26000, 'stock' => 999],
            ['name' => 'Paket Nasi Uduk Ayam Kampung',    'price' => 31000, 'stock' => 999],
            ['name' => 'Paket Nasi Uduk Bebek Goreng',    'price' => 36000, 'stock' => 999],
            ['name' => 'Paket Nasi Ayam Hore',            'price' => 23000, 'stock' => 999],
            ['name' => 'Paket Nasi Ayam',                 'price' => 28000, 'stock' => 999],
            ['name' => 'Paket Nasi Ayam Kremes',          'price' => 30000, 'stock' => 999],
            ['name' => 'Paket Nasi Ayam Rica',            'price' => 30000, 'stock' => 999],
            ['name' => 'Paket Nasi Bebek',                'price' => 33000, 'stock' => 999],
            ['name' => 'Paket Nasi Bebek Rica',           'price' => 35000, 'stock' => 999],
            ['name' => 'Paket Nasi Iga',                  'price' => 36000, 'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Menu Kebakar
        foreach ([
            ['name' => 'Paket Nasi Ayam Hore Bakar',    'price' => 26000, 'stock' => 999],
            ['name' => 'Paket Nasi Ayam Kampung Bakar', 'price' => 31000, 'stock' => 999],
            ['name' => 'Paket Nasi Bebek Bakar',        'price' => 36000, 'stock' => 999],
            ['name' => 'Paket Nasi Iga Bakar',          'price' => 39000, 'stock' => 999],
            ['name' => 'Ampela Bakar',                  'price' => 12000, 'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Menu Spesial
        foreach ([
            ['name' => 'Ayam Betutu 1 Ekor',    'price' => 80000, 'stock' => 999],
            ['name' => 'Sop Iga Uweenaaak',      'price' => 37000, 'stock' => 999],
            ['name' => 'Nasi Goreng Ayam Maya',  'price' => 20000, 'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Sayuran
        foreach ([
            ['name' => 'Kol Goreng',      'price' => 8000,  'stock' => 999],
            ['name' => 'Terong Goreng',   'price' => 8000,  'stock' => 999],
            ['name' => 'Cah Kangkung',    'price' => 12000, 'stock' => 999],
            ['name' => 'Cah Taoge',       'price' => 12000, 'stock' => 999],
            ['name' => 'Terong Thailand', 'price' => 10000, 'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // ========== PRODUK IKJ - INI KEDAI JUS ==========
        // Camilan / Menoe
        foreach ([
            ['name' => 'Roti Bakar Coklat',   'price' => 15000, 'stock' => 999],
            ['name' => 'Roti Bakar Keju',      'price' => 15000, 'stock' => 999],
            ['name' => 'Roti Bakar Mix',       'price' => 17000, 'stock' => 999],
            ['name' => 'Salad Sayur',          'price' => 15000, 'stock' => 999],
            ['name' => 'Salad Sayur Telur',    'price' => 18000, 'stock' => 999],
            ['name' => 'Healthy Brunch',       'price' => 25000, 'stock' => 999],
            ['name' => 'Choco Fros',           'price' => 28000, 'stock' => 999],
            ['name' => 'Tropical Kiss',        'price' => 28000, 'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizIkj->id]));
        }

        // Jus ukuran kecil
        foreach ([
            ['name' => 'Jus Jambu Merah (Kecil)',  'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Melon (Kecil)',         'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Semangka (Kecil)',      'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Buah Naga (Kecil)',     'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Strawberry (Kecil)',    'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Mangga (Kecil)',        'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Nanas (Kecil)',         'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Alpukat (Kecil)',       'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Tomat (Kecil)',         'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Wortel (Kecil)',        'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Sirsak (Kecil)',        'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Pisang (Kecil)',        'price' => 12000, 'stock' => 999],
            ['name' => 'Jus Mix Berry (Kecil)',     'price' => 20000, 'stock' => 999],
            ['name' => 'Jus Mix 2 Buah (Kecil)',   'price' => 14000, 'stock' => 999],
            ['name' => 'Green Detox (Kecil)',       'price' => 16000, 'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizIkj->id]));
        }

        // Jus ukuran besar
        foreach ([
            ['name' => 'Jus Jambu Merah (Besar)',  'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Melon (Besar)',         'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Semangka (Besar)',      'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Buah Naga (Besar)',     'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Strawberry (Besar)',    'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Mangga (Besar)',        'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Nanas (Besar)',         'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Alpukat (Besar)',       'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Tomat (Besar)',         'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Wortel (Besar)',        'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Sirsak (Besar)',        'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Pisang (Besar)',        'price' => 15000, 'stock' => 999],
            ['name' => 'Jus Mix Berry (Besar)',     'price' => 27000, 'stock' => 999],
            ['name' => 'Jus Mix 2 Buah (Besar)',   'price' => 18000, 'stock' => 999],
            ['name' => 'Colagen',                  'price' => 20000, 'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizIkj->id]));
        }

        // Add-on IKJ
        foreach ([
            ['name' => 'Add-on Madu',      'price' => 3000, 'stock' => 999],
            ['name' => 'Add-on Tropicana', 'price' => 2000, 'stock' => 999],
            ['name' => 'Cup Take Away',    'price' => 2000, 'stock' => 999],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizIkj->id]));
        }

        echo "\n✅ Seeder berhasil!\n\n";
        echo "=== SUPERADMIN ===\n";
        echo "superadmin@gvipos.com / password\n\n";
        echo "=== OWNER / ADMIN ===\n";
        echo "owner.ayammaya@gvipos.com / password\n";
        echo "owner.ikj@gvipos.com / password\n";
        echo "admin.cafe@gvipos.com / password\n";
        echo "admin.retail@gvipos.com / password\n";
        echo "admin.service@gvipos.com / password\n\n";
        echo "=== KASIR ===\n";
        echo "kasir.ayammaya@gvipos.com / password\n";
        echo "kasir.ikj@gvipos.com / password\n";
        echo "kasir1.cafe@gvipos.com / password\n";
        echo "kasir1.retail@gvipos.com / password\n";
        echo "kasir1.service@gvipos.com / password\n";
    }
}
