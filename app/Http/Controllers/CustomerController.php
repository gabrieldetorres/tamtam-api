<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $service) {}

    /** GET /api/v1/customers?search=juan */
    public function index(Request $request): JsonResponse
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;

        return response()->json(['data' => $this->service->search($search)]);
    }

    /** POST /api/v1/customers */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'contact' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(['data' => $this->service->create($data)], 201);
    }

    /** GET /api/v1/customers/{id}  (order history and stats arrive with the orders phase) */
    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->service->find($id)]);
    }

    /** PATCH /api/v1/customers/{id}/archive */
    public function archive(string $id): JsonResponse
    {
        $this->service->archive($id);

        return response()->json(null, 204);
    }
}
