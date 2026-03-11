<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'business_id', 'name', 'description', 'sku',
        'price', 'discount_percent', 'discounted_price',
        'stock', 'image', 'is_active',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'integer',
            'discounted_price' => 'integer',
            'discount_percent' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($product) {
            if ($product->discount_percent > 0) {
                $product->discounted_price = (int) round(
                    $product->price * (1 - $product->discount_percent / 100)
                );
            } else {
                $product->discounted_price = $product->price;
            }
        });
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function hasDiscount(): bool
    {
        return $this->discount_percent > 0;
    }

    // Harga final: discounted_price + pajak bisnis
    public function finalPrice(): int
    {
        $base = $this->discounted_price > 0 ? $this->discounted_price : $this->price;
        return $this->business ? $this->business->priceWithTax($base) : $base;
    }
}
