<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessTax extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate'      => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
