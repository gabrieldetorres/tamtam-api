<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    /** GET /api/v1/products */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->list()]);
    }

    /** POST /api/v1/products */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:20'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'item_id' => ['nullable', 'uuid', 'exists:items,id'],
            'qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);

        return response()->json(['data' => $this->service->create($data)], 201);
    }

    /** PUT /api/v1/products/{id}  (send only the fields you want to change) */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'size' => ['sometimes', 'nullable', 'string', 'max:20'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'item_id' => ['sometimes', 'nullable', 'uuid', 'exists:items,id'],
            'qty' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);

        return response()->json(['data' => $this->service->update($id, $data, $this->userId($request))]);
    }

    /** DELETE /api/v1/products/{id}  (archives; past orders keep their details) */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->service->archive($id, $this->userId($request));

        return response()->json(null, 204);
    }
}
