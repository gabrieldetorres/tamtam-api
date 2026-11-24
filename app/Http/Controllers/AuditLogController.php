<?php

namespace App\Http\Controllers;

use App\Repositories\AuditLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private AuditLogRepository $audit) {}

    public function index(Request $request): JsonResponse
    {
        $limit = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:500']])['limit'] ?? 200;

        return response()->json(['data' => $this->audit->latest((int) $limit)]);
    }
}
