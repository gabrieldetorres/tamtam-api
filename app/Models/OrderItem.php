<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One line of an order. name/size/price are snapshots taken at the time of sale. */
class OrderItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'order_items';

    protected $fillable = ['order_id', 'product_id', 'name', 'size', 'price', 'qty'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'qty' => 'integer'];
    }
}
