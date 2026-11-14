<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false; // created_at is filled by the database

    protected $table = 'audit_log';

    protected $fillable = ['kind', 'detail', 'user_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
