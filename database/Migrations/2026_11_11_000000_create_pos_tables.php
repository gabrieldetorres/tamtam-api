<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * TamTam POS tables (translated from schema.sql).
 * Differences from the old schema:
 *  - No triggers: audit logging and stock deduction move into Laravel Services,
 *    inside one database transaction per order.
 *  - items.archived added (archive ingredients instead of deleting them).
 *  - menu.item_id uses RESTRICT, so an ingredient in use cannot be deleted.
 *  - audit_log.user_id added (who did it).
 *  - Indexes, CHECK constraints, and a unique active (name, size) per product.
 *  - RLS enabled with NO policies: the Supabase publishable key cannot touch these
 *    tables. Laravel connects as the database owner, which bypasses RLS.
 */
return new class extends Migration
{
    private array $tables = [
        'items', 'menu', 'customers', 'orders', 'order_items', 'audit_log',
    ];

    public function up(): void
    {
        Schema::create('items', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->text('item');
            $t->decimal('qty', 14, 3)->default(0);
            $t->text('unit')->nullable();
            $t->boolean('archived')->default(false);
            $t->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('menu', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->text('name');
            $t->text('size')->default('M');
            $t->decimal('price', 10, 2);
            $t->foreignUuid('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $t->decimal('qty', 14, 3)->default(0); // amount of ingredient used per cup
            $t->boolean('archived')->default(false);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->text('name');
            $t->text('contact')->nullable();
            $t->boolean('archived')->default(false);
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->bigInteger('order_no')->generatedAs()->always()->unique();
            $t->foreignUuid('customer_id')->nullable()->constrained('customers');
            $t->text('status')->default('paid');
            $t->decimal('total', 10, 2)->default(0);
            $t->decimal('tendered', 10, 2)->nullable();
            $t->decimal('change_due', 10, 2)->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('paid_at')->nullable();
        });

        // name/size/price are snapshots taken at time of sale
        Schema::create('order_items', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $t->foreignUuid('product_id')->nullable()->constrained('menu')->nullOnDelete();
            $t->text('name');
            $t->text('size')->nullable();
            $t->decimal('price', 10, 2);
            $t->integer('qty');
        });

        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->text('kind');
            $t->text('detail');
            $t->uuid('user_id')->nullable(); // Supabase auth user who did it
            $t->timestampTz('created_at')->useCurrent();
        });

        // CHECK constraints (the database refuses bad data even if the code has a bug)
        DB::statement('alter table items add constraint items_qty_nonneg check (qty >= 0)');
        DB::statement('alter table menu add constraint menu_price_nonneg check (price >= 0)');
        DB::statement('alter table menu add constraint menu_qty_nonneg check (qty >= 0)');
        DB::statement("alter table orders add constraint orders_status_valid check (status in ('paid','void'))");
        DB::statement('alter table orders add constraint orders_total_nonneg check (total >= 0)');
        DB::statement('alter table order_items add constraint order_items_price_nonneg check (price >= 0)');
        DB::statement('alter table order_items add constraint order_items_qty_pos check (qty > 0)');

        // Indexes (Postgres does not index foreign keys automatically)
        DB::statement('create unique index menu_name_size_active on menu (name, size) where archived = false');
        DB::statement('create index menu_item_id_idx on menu (item_id)');
        DB::statement('create index orders_status_created_idx on orders (status, created_at)');
        DB::statement('create index orders_customer_id_idx on orders (customer_id)');
        DB::statement('create index order_items_order_id_idx on order_items (order_id)');
        DB::statement('create index order_items_product_id_idx on order_items (product_id)');
        DB::statement('create index audit_log_created_idx on audit_log (created_at desc)');

        // Lock the tables away from Supabase's public API (no policies = no access)
        foreach (array_merge($this->tables, ['migrations']) as $table) {
            DB::statement("alter table \"{$table}\" enable row level security");
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
