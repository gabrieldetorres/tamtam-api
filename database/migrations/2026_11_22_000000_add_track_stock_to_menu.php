<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu', function (Blueprint $t) {
            $t->boolean('track_stock')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('menu', function (Blueprint $t) {
            $t->dropColumn('track_stock');
        });
    }
};
