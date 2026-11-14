<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Support\Collection;

class AuditLogRepository
{
    public function record(string $kind, string $detail, ?string $userId): void
    {
        AuditLog::create(['kind' => $kind, 'detail' => $detail, 'user_id' => $userId]);
    }

    public function latest(int $limit = 200): Collection
    {
        return AuditLog::query()->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
    }
}
