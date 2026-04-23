<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Business extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'logo', 'qris_image',
        'is_active', 'owner_id',
        // 'tax_name', 'tax_rate',
        'address', 'phone', 'city',
    ];

    protected $appends = ['logo_url', 'qris_image_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function getQrisImageUrlAttribute(): ?string
    {
        return $this->qris_image ? asset('storage/' . $this->qris_image) : null;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'tax_rate'  => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($business) {
            if (empty($business->slug)) {
                $business->slug = Str::slug($business->name);
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // public function priceWithTax(int $price): int
    // {
    //     if ($this->tax_rate <= 0) return $price;
    //     return (int) round($price * (1 + $this->tax_rate / 100));
    // }
    public function priceWithTax(int $price): int
    {
        $totalRate = $this->activeTaxes()->sum('rate');
        if ($totalRate <= 0) return $price;
        return (int) round($price * (1 + $totalRate / 100));
    }

    // Tambah relasi ini
    public function taxes()
    {
        return $this->hasMany(BusinessTax::class);
    }

    public function activeTaxes()
    {
        return $this->taxes()->where('is_active', true);
    }
}
