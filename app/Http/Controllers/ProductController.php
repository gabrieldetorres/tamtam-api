<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->list()]);
    }

    
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:20'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'ingredients' => ['nullable', 'array', 'max:20'],
            'ingredients.*.item_id' => ['required', 'uuid', 'distinct'],
            'ingredients.*.qty' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
        ]);

        return response()->json(['data' => $this->service->create($data)], 201);
    }

  
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'size' => ['sometimes', 'nullable', 'string', 'max:20'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'ingredients' => ['sometimes', 'nullable', 'array', 'max:20'],
            'ingredients.*.item_id' => ['required', 'uuid', 'distinct'],
            'ingredients.*.qty' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
        ]);

        return response()->json(['data' => $this->service->update($id, $data, $this->userId($request))]);
    }

    
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->service->archive($id, $this->userId($request));

        return response()->json(null, 204);
    }
}
