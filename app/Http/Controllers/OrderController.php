<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $service) {}

    
    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'status' => ['nullable', 'in:paid,void'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json(['data' => $this->service->list($v['status'] ?? null, (int) ($v['limit'] ?? 50))]);
    }

    
    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->service->find($id)]);
    }

    
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + [
            'tendered' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        [$order, $created] = $this->service->place($data, $this->userId($request));

        return response()->json(['data' => $order], $created ? 201 : 200);
    }

    
    public function void(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        [$order, $created] = $this->service->void($data, $this->userId($request));

        return response()->json(['data' => $order], $created ? 201 : 200);
    }

    private function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'uuid'],
            'client_ref' => ['nullable', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
