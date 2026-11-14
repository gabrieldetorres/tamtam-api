<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'customers';

    protected $fillable = ['name', 'contact', 'archived'];

    protected function casts(): array
    {
        return ['archived' => 'boolean'];
    }
}
