<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A drink on the menu (table: menu). item_id + qty = ingredient used per cup. */
class Product extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'menu';

    protected $fillable = ['name', 'size', 'price', 'item_id', 'qty', 'archived'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'qty' => 'decimal:3', 'archived' => 'boolean'];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
