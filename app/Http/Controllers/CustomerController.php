<?php

namespace App\Http\Controllers;

use App\Services\CustomerProfileService;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $service,
        private CustomerProfileService $profiles,
    ) {}

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

    /** GET /api/v1/customers/{id} */
    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->service->find($id)]);
    }

    /** GET /api/v1/customers/{id}/profile  (stats, favourites, order history) */
    public function profile(string $id): JsonResponse
    {
        return response()->json(['data' => $this->profiles->profile($id)]);
    }

    /** PATCH /api/v1/customers/{id}/archive */
    public function archive(string $id): JsonResponse
    {
        $this->service->archive($id);

        return response()->json(null, 204);
    }
}
