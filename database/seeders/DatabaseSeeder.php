<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Business;
use App\Models\Product;
use App\Models\Category;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ========== CATEGORIES ==========
        // Panggil CategorySeeder dulu, lalu tambah kategori extra pakai firstOrCreate
        $this->call(CategorySeeder::class);

        // Kategori dari CategorySeeder (sudah ada)
        $catMakanan = Category::firstOrCreate(['name' => 'Makanan'],  ['color' => '#DC2626', 'icon' => '🍱']);
        $catMinuman = Category::firstOrCreate(['name' => 'Minuman'],  ['color' => '#2563EB', 'icon' => '🥤']);
        $catCamilan = Category::firstOrCreate(['name' => 'Snack'],    ['color' => '#D97706', 'icon' => '🍪']);
        $catAddon   = Category::firstOrCreate(['name' => 'Lainnya'],  ['color' => '#6B7280', 'icon' => '➕']);

        // Kategori tambahan
        $catPaket   = Category::firstOrCreate(['name' => 'Paket'],    ['color' => '#7C3AED', 'icon' => '🍽️']);
        $catSayur   = Category::firstOrCreate(['name' => 'Sayuran'],  ['color' => '#059669', 'icon' => '🥬']);
        $catJus     = Category::firstOrCreate(['name' => 'Jus'],      ['color' => '#0891B2', 'icon' => '🍹']);
        $catMerch   = Category::firstOrCreate(['name' => 'Merch'],    ['color' => '#DB2777', 'icon' => '👕']);
        $catService = Category::firstOrCreate(['name' => 'Layanan'],  ['color' => '#EA580C', 'icon' => '🔧']);
        $catParts   = Category::firstOrCreate(['name' => 'Sparepart'],['color' => '#4F46E5', 'icon' => '⚙️']);

        // ========== SUPERADMIN ==========
        User::create([
            'name'      => 'Super Admin',
            'email'     => 'superadmin@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'superadmin',
            'is_active' => true,
        ]);

        // ========== DEMO OWNERS ==========
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

        $ownerAyamMaya = User::create([
            'name'      => 'Owner Ayam Maya',
            'email'     => 'owner.ayammaya@gvipos.com',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

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

        // ========== KASIR ==========
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

        User::create([
            'name'        => 'Kasir Ayam Maya',
            'email'       => 'kasir.ayammaya@gvipos.com',
            'password'    => Hash::make('password'),
            'role'        => 'kasir',
            'is_active'   => true,
            'business_id' => $bizAyamMaya->id,
            'owner_id'    => $ownerAyamMaya->id,
        ]);

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
            ['name' => 'Kopi Americano',  'price' => 25000, 'stock' => 50,  'category_id' => $catMinuman->id],
            ['name' => 'Kopi Latte',      'price' => 30000, 'stock' => 50,  'category_id' => $catMinuman->id],
            ['name' => 'Cappuccino',      'price' => 32000, 'stock' => 50,  'category_id' => $catMinuman->id],
            ['name' => 'Es Teh Manis',    'price' => 10000, 'stock' => 100, 'category_id' => $catMinuman->id],
            ['name' => 'Jus Alpukat',     'price' => 20000, 'stock' => 30,  'category_id' => $catMinuman->id],
            ['name' => 'Croissant',       'price' => 22000, 'stock' => 20,  'category_id' => $catCamilan->id, 'discount_percent' => 10],
            ['name' => 'Cheesecake',      'price' => 35000, 'stock' => 15,  'category_id' => $catCamilan->id],
            ['name' => 'Nasi Goreng',     'price' => 28000, 'stock' => 30,  'category_id' => $catMakanan->id],
            ['name' => 'Pasta Carbonara', 'price' => 45000, 'stock' => 20,  'category_id' => $catMakanan->id, 'discount_percent' => 5],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $cafe->id]));
        }

        // ========== PRODUK GVI RETAIL ==========
        foreach ([
            ['name' => 'Kaos GVI Logo',   'price' => 85000,  'stock' => 30,  'category_id' => $catMerch->id],
            ['name' => 'Tote Bag GVI',    'price' => 65000,  'stock' => 25,  'category_id' => $catMerch->id, 'discount_percent' => 15],
            ['name' => 'Mug GVI',         'price' => 75000,  'stock' => 20,  'category_id' => $catMerch->id],
            ['name' => 'Stiker Pack GVI', 'price' => 15000,  'stock' => 100, 'category_id' => $catMerch->id],
            ['name' => 'Hoodie GVI',      'price' => 195000, 'stock' => 15,  'category_id' => $catMerch->id, 'discount_percent' => 10],
            ['name' => 'Topi GVI',        'price' => 95000,  'stock' => 20,  'category_id' => $catMerch->id],
            ['name' => 'Payung GVI',      'price' => 120000, 'stock' => 10,  'category_id' => $catMerch->id],
            ['name' => 'Notebook GVI',    'price' => 45000,  'stock' => 40,  'category_id' => $catMerch->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $retail->id]));
        }

        // ========== PRODUK GVI SERVICE ==========
        foreach ([
            ['name' => 'Instalasi Digital Signage',  'price' => 500000,  'stock' => 999, 'category_id' => $catService->id],
            ['name' => 'Konfigurasi LED Controller', 'price' => 350000,  'stock' => 999, 'category_id' => $catService->id],
            ['name' => 'Maintenance Tahunan',        'price' => 1200000, 'stock' => 999, 'category_id' => $catService->id],
            ['name' => 'Training Operator',          'price' => 750000,  'stock' => 999, 'category_id' => $catService->id],
            ['name' => 'Konsultasi Teknis',          'price' => 250000,  'stock' => 999, 'category_id' => $catService->id, 'discount_percent' => 20],
            ['name' => 'Penggantian Modul LED',      'price' => 450000,  'stock' => 50,  'category_id' => $catParts->id],
            ['name' => 'Kabel HDMI 5m',              'price' => 85000,   'stock' => 30,  'category_id' => $catParts->id],
            ['name' => 'Power Supply LED',           'price' => 320000,  'stock' => 20,  'category_id' => $catParts->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $service->id]));
        }

        // ========== PRODUK AYAM MAYA ==========

        // Minuman
        foreach ([
            ['name' => 'Es Coklat Cao',         'price' => 9000,  'stock' => 999, 'category_id' => $catMinuman->id],
            ['name' => 'Es Coklat Cao Jumbo',   'price' => 15000, 'stock' => 999, 'category_id' => $catMinuman->id],
            ['name' => 'Jeruk Es/Hangat',       'price' => 8000,  'stock' => 999, 'category_id' => $catMinuman->id],
            ['name' => 'Jeruk Jumbo Es/Hangat', 'price' => 13000, 'stock' => 999, 'category_id' => $catMinuman->id],
            ['name' => 'Teh Es/Hangat',         'price' => 4000,  'stock' => 999, 'category_id' => $catMinuman->id],
            ['name' => 'Teh Jumbo Es/Hangat',   'price' => 6000,  'stock' => 999, 'category_id' => $catMinuman->id],
            ['name' => 'Lemon Tea Es/Hangat',   'price' => 10000, 'stock' => 999, 'category_id' => $catMinuman->id],
            ['name' => 'Es Beras Kencur/Sinom', 'price' => 9000,  'stock' => 999, 'category_id' => $catMinuman->id],
            ['name' => 'Air Mineral',           'price' => 5000,  'stock' => 999, 'category_id' => $catMinuman->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Menu Godaan (makanan satuan)
        foreach ([
            ['name' => 'Ayam Maya',           'price' => 20000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ayam Kremes',         'price' => 28000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ayam Goreng',         'price' => 26000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ayam Rica',           'price' => 28000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ceker Rica',          'price' => 12000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Bebek Goreng',        'price' => 31000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Iga Goreng',          'price' => 34000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ayam Hore Bakar',     'price' => 23000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ayam Kampung Bakar',  'price' => 29000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Bebek Bakar',         'price' => 34000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Iga Bakar',           'price' => 37000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Lele Goreng',         'price' => 14000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Kepala Bebek (2pcs)', 'price' => 10000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Kepala Ayam (2pcs)',  'price' => 10000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ampela (2pcs)',       'price' => 10000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ampela Rica (2pcs)',  'price' => 12000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Ayam Pok Pok',       'price' => 15000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Chicken Egg Roll',   'price' => 15000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Telor Dadar/Ceplok', 'price' => 5000,  'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Tempe/Tahu (2pcs)',  'price' => 5000,  'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Nasi Putih',         'price' => 5000,  'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Nasi Uduk',          'price' => 7000,  'stock' => 999, 'category_id' => $catMakanan->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Menu Paket
        foreach ([
            ['name' => 'Paket Nasi Uduk Ayam Hore',    'price' => 26000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Uduk Ayam Kampung', 'price' => 31000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Uduk Bebek Goreng', 'price' => 36000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Ayam Hore',         'price' => 23000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Ayam',              'price' => 28000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Ayam Kremes',       'price' => 30000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Ayam Rica',         'price' => 30000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Bebek',             'price' => 33000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Bebek Rica',        'price' => 35000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Iga',               'price' => 36000, 'stock' => 999, 'category_id' => $catPaket->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Menu Kebakar
        foreach ([
            ['name' => 'Paket Nasi Ayam Hore Bakar',    'price' => 26000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Ayam Kampung Bakar', 'price' => 31000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Bebek Bakar',        'price' => 36000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Paket Nasi Iga Bakar',          'price' => 39000, 'stock' => 999, 'category_id' => $catPaket->id],
            ['name' => 'Ampela Bakar',                  'price' => 12000, 'stock' => 999, 'category_id' => $catMakanan->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Menu Spesial
        foreach ([
            ['name' => 'Ayam Betutu 1 Ekor',   'price' => 80000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Sop Iga Uweenaaak',     'price' => 37000, 'stock' => 999, 'category_id' => $catMakanan->id],
            ['name' => 'Nasi Goreng Ayam Maya', 'price' => 20000, 'stock' => 999, 'category_id' => $catMakanan->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // Sayuran
        foreach ([
            ['name' => 'Kol Goreng',      'price' => 8000,  'stock' => 999, 'category_id' => $catSayur->id],
            ['name' => 'Terong Goreng',   'price' => 8000,  'stock' => 999, 'category_id' => $catSayur->id],
            ['name' => 'Cah Kangkung',    'price' => 12000, 'stock' => 999, 'category_id' => $catSayur->id],
            ['name' => 'Cah Taoge',       'price' => 12000, 'stock' => 999, 'category_id' => $catSayur->id],
            ['name' => 'Terong Thailand', 'price' => 10000, 'stock' => 999, 'category_id' => $catSayur->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizAyamMaya->id]));
        }

        // ========== PRODUK IKJ ==========

        // Camilan
        foreach ([
            ['name' => 'Roti Bakar Coklat', 'price' => 15000, 'stock' => 999, 'category_id' => $catCamilan->id],
            ['name' => 'Roti Bakar Keju',   'price' => 15000, 'stock' => 999, 'category_id' => $catCamilan->id],
            ['name' => 'Roti Bakar Mix',    'price' => 17000, 'stock' => 999, 'category_id' => $catCamilan->id],
            ['name' => 'Salad Sayur',       'price' => 15000, 'stock' => 999, 'category_id' => $catCamilan->id],
            ['name' => 'Salad Sayur Telur', 'price' => 18000, 'stock' => 999, 'category_id' => $catCamilan->id],
            ['name' => 'Healthy Brunch',    'price' => 25000, 'stock' => 999, 'category_id' => $catCamilan->id],
            ['name' => 'Choco Fros',        'price' => 28000, 'stock' => 999, 'category_id' => $catCamilan->id],
            ['name' => 'Tropical Kiss',     'price' => 28000, 'stock' => 999, 'category_id' => $catCamilan->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizIkj->id]));
        }

        // Jus kecil
        foreach ([
            ['name' => 'Jus Jambu Merah (Kecil)', 'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Melon (Kecil)',        'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Semangka (Kecil)',     'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Buah Naga (Kecil)',    'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Strawberry (Kecil)',   'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Mangga (Kecil)',       'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Nanas (Kecil)',        'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Alpukat (Kecil)',      'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Tomat (Kecil)',        'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Wortel (Kecil)',       'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Sirsak (Kecil)',       'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Pisang (Kecil)',       'price' => 12000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Mix Berry (Kecil)',    'price' => 20000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Mix 2 Buah (Kecil)',  'price' => 14000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Green Detox (Kecil)',      'price' => 16000, 'stock' => 999, 'category_id' => $catJus->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizIkj->id]));
        }

        // Jus besar
        foreach ([
            ['name' => 'Jus Jambu Merah (Besar)', 'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Melon (Besar)',        'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Semangka (Besar)',     'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Buah Naga (Besar)',    'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Strawberry (Besar)',   'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Mangga (Besar)',       'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Nanas (Besar)',        'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Alpukat (Besar)',      'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Tomat (Besar)',        'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Wortel (Besar)',       'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Sirsak (Besar)',       'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Pisang (Besar)',       'price' => 15000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Mix Berry (Besar)',    'price' => 27000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Jus Mix 2 Buah (Besar)',  'price' => 18000, 'stock' => 999, 'category_id' => $catJus->id],
            ['name' => 'Colagen',                 'price' => 20000, 'stock' => 999, 'category_id' => $catJus->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizIkj->id]));
        }

        // Add-on IKJ
        foreach ([
            ['name' => 'Add-on Madu',      'price' => 3000, 'stock' => 999, 'category_id' => $catAddon->id],
            ['name' => 'Add-on Tropicana', 'price' => 2000, 'stock' => 999, 'category_id' => $catAddon->id],
            ['name' => 'Cup Take Away',    'price' => 2000, 'stock' => 999, 'category_id' => $catAddon->id],
        ] as $p) {
            Product::create(array_merge($p, ['business_id' => $bizIkj->id]));
        }

        echo "\n✅ Seeder berhasil!\n\n";
        echo "=== KATEGORI ===\n";
        echo "🍱 Makanan (merah)  🥤 Minuman (biru)   🍽️ Paket (ungu)\n";
        echo "🥬 Sayuran (hijau)  🍪 Camilan (kuning) 🍹 Jus (cyan)\n";
        echo "➕ Add-on (abu)     👕 Merch (pink)     🔧 Layanan (oranye)\n";
        echo "⚙️ Sparepart (indigo)\n\n";
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
