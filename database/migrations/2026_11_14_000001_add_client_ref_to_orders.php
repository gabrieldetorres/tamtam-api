<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * client_ref = a random id the browser makes for each checkout.
 * If the same checkout is sent twice (double tap, retry after a bad connection),
 * the database refuses the duplicate and the API returns the first order instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->text('client_ref')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn('client_ref');
        });
    }
};
