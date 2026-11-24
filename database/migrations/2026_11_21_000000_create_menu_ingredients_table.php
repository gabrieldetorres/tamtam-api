<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_ingredients', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->foreignUuid('menu_id')->constrained('menu')->cascadeOnDelete();
            $t->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $t->decimal('qty', 14, 3);
            $t->unique(['menu_id', 'item_id']); // an ingredient appears once per recipe
        });

        DB::statement('alter table menu_ingredients add constraint menu_ingredients_qty_pos check (qty > 0)');
        DB::statement('create index menu_ingredients_item_id_idx on menu_ingredients (item_id)');

        // keep every existing drink-to-ingredient link
        DB::statement('
            insert into menu_ingredients (menu_id, item_id, qty)
            select id, item_id, qty from menu where item_id is not null and qty > 0
        ');

        // same lock-down as every other table: the browser cannot touch it directly
        DB::statement('alter table menu_ingredients enable row level security');
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_ingredients');
    }
};
