<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'gvi_item_variant_id',
        'gvi_item_variant_name',
        'product_name',
        'product_sku',
        'price',
        'quantity',
        'subtotal',
        'source',
        'kitchen_status',
        'cooking_started_at',
        'cooking_done_at',
        'cooking_duration_seconds',
        'pause_duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'subtotal' => 'integer',
            'cooking_started_at' => 'datetime',
            'cooking_done_at'    => 'datetime',
            'cooking_duration_seconds' => 'integer',
            'pause_duration_seconds'   => 'integer',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
