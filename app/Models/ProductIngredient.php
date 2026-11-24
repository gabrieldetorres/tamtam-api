<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ProductIngredient extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'menu_ingredients';

    protected $fillable = ['item_id', 'qty'];

    protected $hidden = ['id', 'menu_id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
