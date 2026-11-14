<?php

namespace App\Services;

use App\Models\Item;
use App\Repositories\AuditLogRepository;
use App\Repositories\IngredientRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Business rules for ingredient stock. */
class IngredientService
{
    public function __construct(
        private IngredientRepository $ingredients,
        private ProductRepository $products,
        private AuditLogRepository $audit,
    ) {}

    public function list(): Collection
    {
        return $this->ingredients->allActive();
    }

    public function create(array $data): Item
    {
        return $this->ingredients->create([
            'item' => trim($data['item']),
            'qty' => $data['qty'] ?? 0,
            'unit' => isset($data['unit']) ? trim($data['unit']) : null,
        ]);
    }

    public function update(string $id, array $data, string $userId): Item
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $item = $this->ingredients->findActiveForUpdate($id);

            if (array_key_exists('item', $data)) {
                $data['item'] = trim($data['item']);
            }
            if (array_key_exists('unit', $data) && $data['unit'] !== null) {
                $data['unit'] = trim($data['unit']);
            }

            $oldQty = $item->qty;
            $updated = $this->ingredients->update($item, $data);

            // Stock changes made by hand are logged (who, what, from, to).
            if (array_key_exists('qty', $data) && $updated->qty !== $oldQty) {
                $this->audit->record(
                    'stock adjust',
                    trim($updated->item.': '.$oldQty.' to '.$updated->qty.' '.($updated->unit ?? '')),
                    $userId
                );
            }

            return $updated;
        });
    }

    /** Archived, never deleted. Blocked while an active drink still uses it. */
    public function archive(string $id, string $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $item = $this->ingredients->findActiveForUpdate($id);

            $usedBy = $this->products->namesUsingIngredient($item->id);
            if ($usedBy) {
                throw ValidationException::withMessages([
                    'ingredient' => 'Still used by: '.implode(', ', $usedBy).'. Change those drinks first.',
                ]);
            }

            $this->ingredients->archive($item);
            $this->audit->record('ingredient archived', $item->item, $userId);
        });
    }
}
