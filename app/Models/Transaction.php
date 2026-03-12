<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'invoice_number',
        'user_id',
        'business_id',
        'subtotal',
        'tax',
        'discount',
        'total',
        'payment_method',
        'status',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'midtrans_snap_token',
        'notes',
        'cancel_reason',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'cancelled_at'  => 'datetime',
            'subtotal' => 'integer',
            'tax' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    // Generate invoice number otomatis
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ymd');
        $last = self::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $number = $last
            ? (int) substr($last->invoice_number, -4) + 1
            : 1;

        return $prefix . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
