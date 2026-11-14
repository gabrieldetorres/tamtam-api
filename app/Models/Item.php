<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** An ingredient in stock (table: items). */
class Item extends Model
{
    use HasUuids;

    public const CREATED_AT = null; // this table only has updated_at

    protected $table = 'items';

    protected $fillable = ['item', 'qty', 'unit', 'archived'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'archived' => 'boolean', 'updated_at' => 'datetime'];
    }
}
