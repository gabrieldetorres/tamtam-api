<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    public function search(?string $term, int $limit = 50): Collection
    {
        $query = Customer::query()->where('archived', false);

        if ($term !== null && $term !== '') {
            // escape LIKE wildcards so "%" or "_" typed by the user are plain characters
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('contact', 'ilike', $like));
        }

        return $query->orderBy('name')->limit($limit)->get();
    }

    public function findActive(string $id): Customer
    {
        return Customer::query()->where('archived', false)->findOrFail($id);
    }

    public function create(array $data): Customer
    {
        return Customer::create($data)->fresh();
    }

    public function archive(Customer $customer): void
    {
        $customer->update(['archived' => true]);
    }
}
