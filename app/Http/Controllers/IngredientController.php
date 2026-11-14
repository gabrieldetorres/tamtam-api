<?php

namespace App\Http\Controllers;

use App\Services\IngredientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function __construct(private IngredientService $service) {}

    /** GET /api/v1/ingredients */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->list()]);
    }

    /** POST /api/v1/ingredients */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item' => ['required', 'string', 'max:100'],
            'qty' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'unit' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json(['data' => $this->service->create($data)], 201);
    }

    /** PUT /api/v1/ingredients/{id}  (rename, change unit, or set a new quantity) */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'item' => ['sometimes', 'required', 'string', 'max:100'],
            'qty' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999999'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        return response()->json(['data' => $this->service->update($id, $data, $this->userId($request))]);
    }

    /** DELETE /api/v1/ingredients/{id}  (archives; blocked while a drink uses it) */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->service->archive($id, $this->userId($request));

        return response()->json(null, 204);
    }
}
