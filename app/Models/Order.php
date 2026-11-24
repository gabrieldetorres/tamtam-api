<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUuids;

    public $timestamps = false; 

    protected $table = 'orders';

    protected $fillable = [
        'customer_id', 'status', 'total', 'tendered', 'change_due', 'paid_at', 'client_ref',
    ];

    protected function casts(): array
    {
        return [
            'order_no' => 'integer',
            'total' => 'decimal:2',
            'tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'created_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
