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
        'notes',
        'cancel_reason',
        'table_number',
        'customer_name',
        'queue_color',
        'queue_status',
        'ready_at',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at'      => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal'     => 'integer',
            'tax'          => 'integer',
            'discount'     => 'integer',
            'total'        => 'integer',
            'ready_at'     => 'datetime',
        ];
    }

    // ── Palette 30 warna untuk antrian kitchen ────────────────────────────
    const QUEUE_COLOR_PALETTE = [
        '#FF6B6B', '#FF8E53', '#FFD93D', '#6BCB77', '#4D96FF',
        '#C77DFF', '#FF6FC8', '#00C9A7', '#F4A261', '#A8DADC',
        '#E63946', '#2EC4B6', '#FFBF69', '#CBF3F0', '#9BF6FF',
        '#BDE0FE', '#FFAFCC', '#CDB4DB', '#A7C957', '#BC6C25',
        '#52B788', '#F72585', '#7209B7', '#3A86FF', '#FB5607',
        '#8338EC', '#06D6A0', '#FFB703', '#D62828', '#457B9D',
    ];

    /**
     * Assign warna antrian otomatis dari palette.
     * Warna dipilih dari yang sedang tidak dipakai transaksi aktif.
     * Kalau semua 30 terpakai, fallback ke warna transaksi aktif tertua
     * (yang paling lama, kemungkinan besar sudah hampir selesai).
     */
    public static function assignQueueColor(?int $businessId): string
    {
        // Ambil warna yang sedang aktif di bisnis yang sama
        $activeColors = self::whereIn('status', ['paid', 'open_bill'])
            ->when($businessId, fn($q) => $q->where('business_id', $businessId))
            ->whereNotNull('queue_color')
            ->whereHas('items', fn($q) => $q->whereIn('kitchen_status', ['queued', 'cooking', 'paused']))
            ->whereDate('created_at', today())
            ->pluck('queue_color')
            ->toArray();

        // Cari warna dari palette yang belum dipakai
        foreach (self::QUEUE_COLOR_PALETTE as $color) {
            if (!in_array($color, $activeColors)) { 
                return $color;
            }
        }

        // Fallback: semua 30 warna terpakai — ambil warna transaksi aktif tertua
        // (paling mungkin selesai duluan, jadi warnanya "aman" untuk di-recycle)
        $oldest = self::whereIn('status', ['pending', 'open_bill'])
            ->when($businessId, fn($q) => $q->where('business_id', $businessId))
            ->whereNotNull('queue_color')
            ->orderBy('created_at', 'asc')
            ->value('queue_color');

        return $oldest ?? self::QUEUE_COLOR_PALETTE[0];
    }

    // ── Relationships ─────────────────────────────────────────────────────
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

    // ── Generate invoice number otomatis ──────────────────────────────────
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ymd');
        $last   = self::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $number = $last
            ? (int) substr($last->invoice_number, -4) + 1
            : 1;

        return $prefix . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
