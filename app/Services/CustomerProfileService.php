<?php

namespace App\Services;

use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;

/** Customer profile: stats, favourites and history (for "repeat last order"). */
class CustomerProfileService
{
    public function __construct(
        private CustomerRepository $customers,
        private OrderRepository $orders,
    ) {}

    public function profile(string $id): array
    {
        $customer = $this->customers->findActive($id);

        return [
            'customer' => $customer,
            'stats' => $this->orders->customerStats($id),
            'top_products' => $this->orders->customerTopProducts($id, 3),
            'history' => $this->orders->customerHistory($id, 50), // newest first; items include product_id
        ];
    }
}
