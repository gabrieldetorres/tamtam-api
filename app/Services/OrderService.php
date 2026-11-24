<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\AuditLogRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class OrderService
{
    private const MAX_TOTAL_CENTS = 9_999_999_999; // 99,999,999.99 (column limit)

    public function __construct(
        private OrderRepository $orders,
        private ProductRepository $products,
        private StockRepository $stock,
        private CustomerRepository $customers,
        private AuditLogRepository $audit,
    ) {}

    /** @return array{0: Order, 1: bool} the order, and true if it was newly created */
    public function place(array $input, string $userId): array
    {
        return $this->save($input, 'paid', $userId);
    }

    /** Cancelled cart: recorded and logged, but no stock is used. */
    public function void(array $input, string $userId): array
    {
        return $this->save($input, 'void', $userId);
    }

    public function list(?string $status, int $limit): \Illuminate\Database\Eloquent\Collection
    {
        return $this->orders->latest($status, $limit);
    }

    public function find(string $id): Order
    {
        return $this->orders->find($id);
    }

    private function save(array $input, string $status, string $userId): array
    {
        $ref = $input['client_ref'] ?? null;

        // Same checkout sent twice? Return the first order.
        if ($ref && ($existing = $this->orders->findByClientRef($ref))) {
            return [$existing, false];
        }

        try {
            $order = DB::transaction(fn () => $this->record($input, $status, $userId));
        } catch (UniqueConstraintViolationException $e) {
            // Two identical requests arrived at the same moment; the other one won.
            $existing = $ref ? $this->orders->findByClientRef($ref) : null;
            if (! $existing) {
                throw $e;
            }

            return [$existing, false];
        }

        return [$order, true];
    }

    private function record(array $input, string $status, string $userId): Order
    {
        $lines = $this->mergeLines($input['items']);
        $products = $this->products->activeByIds(array_keys($lines));
        $customerId = $this->checkCustomer($input['customer_id'] ?? null);

        $totalCents = 0;
        $rows = [];
        $needs = []; // ingredient id => amount needed, in thousandths

        foreach ($lines as $productId => $qty) {
            $product = $products->get($productId);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'A product on this order is no longer on the menu. Refresh the menu and try again.',
                ]);
            }

            // A product without a recipe would be sold without using any stock, so it cannot be sold.
            if ($status === 'paid' && $product->ingredients->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => $product->name.' has no recipe yet. Set its recipe in Menu first.',
                ]);
            }

            $totalCents += $this->cents($product->price) * $qty;

            $rows[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'size' => $product->size,
                'price' => $product->price,
                'qty' => $qty,
            ];

            // every ingredient in the recipe is used once per serving
            foreach ($product->ingredients as $recipeLine) {
                $needs[$recipeLine->item_id] = ($needs[$recipeLine->item_id] ?? 0)
                    + $this->milli($recipeLine->qty) * $qty;
            }
        }

        if ($totalCents > self::MAX_TOTAL_CENTS) {
            throw ValidationException::withMessages(['items' => 'The order total is too large.']);
        }

        $order = [
            'customer_id' => $customerId,
            'status' => $status,
            'total' => $this->money($totalCents),
            'client_ref' => $input['client_ref'] ?? null,
        ];

        if ($status === 'paid') {
            $tenderedCents = $this->cents($input['tendered']);

            if ($tenderedCents < $totalCents) {
                throw ValidationException::withMessages(['tendered' => 'The cash given is less than the total.']);
            }

            $this->useStock($needs);

            $order['tendered'] = $this->money($tenderedCents);
            $order['change_due'] = $this->money($tenderedCents - $totalCents);
            $order['paid_at'] = now();
        }

        $created = $this->orders->create($order, $rows);

        // Replaces the old log_void() database trigger.
        if ($status === 'void') {
            $this->audit->record('void order', 'Order #'.$created->order_no.' total '.$created->total, $userId);
        }

        return $created;
    }

    /** Checks there is enough of EVERY ingredient first, then deducts them all. */
    private function useStock(array $needs): void
    {
        if (! $needs) {
            return;
        }

        ksort($needs);
        $stock = $this->stock->lockForUpdate(array_keys($needs));

        foreach ($needs as $itemId => $needed) {
            $item = $stock->get($itemId);

            if (! $item) {
                throw ValidationException::withMessages(['items' => 'An ingredient for this order is no longer available.']);
            }

            if ($this->milli($item->qty) < $needed) {
                throw ValidationException::withMessages([
                    'items' => 'Not enough '.$item->item.': this order needs '.$this->fmt($needed)
                        .' '.($item->unit ?? '').', only '.$item->qty.' left.',
                ]);
            }
        }

        foreach ($needs as $itemId => $needed) {
            $this->stock->deduct($itemId, $this->fmt($needed));
        }
    }

    /** The same product listed twice becomes one line. */
    private function mergeLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $id = $item['product_id'];
            $lines[$id] = ($lines[$id] ?? 0) + (int) $item['qty'];
        }

        return $lines;
    }

    private function checkCustomer(?string $customerId): ?string
    {
        if ($customerId === null) {
            return null;
        }

        try {
            return $this->customers->findActive($customerId)->id;
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['customer_id' => 'That customer does not exist or is archived.']);
        }
    }

    // --- money and quantity math in whole numbers (no floating-point drift) ---

    private function cents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function milli(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 1000);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function fmt(int $milli): string
    {
        return number_format($milli / 1000, 3, '.', '');
    }
}
