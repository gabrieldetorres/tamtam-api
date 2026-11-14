<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    public function __construct(private CustomerRepository $customers) {}

    public function search(?string $term): Collection
    {
        return $this->customers->search($term !== null ? trim($term) : null);
    }

    public function find(string $id): Customer
    {
        return $this->customers->findActive($id);
    }

    public function create(array $data): Customer
    {
        return $this->customers->create([
            'name' => trim($data['name']),
            'contact' => isset($data['contact']) ? trim($data['contact']) : null,
        ]);
    }

    public function archive(string $id): void
    {
        $this->customers->archive($this->customers->findActive($id));
    }
}
