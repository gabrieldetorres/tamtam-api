<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Product extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'menu';

    protected $fillable = ['name', 'size', 'price', 'archived'];

    
    protected $hidden = ['item_id', 'qty'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'archived' => 'boolean'];
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(ProductIngredient::class, 'menu_id');
    }
}
