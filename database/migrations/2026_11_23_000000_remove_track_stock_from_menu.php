<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('menu', 'track_stock')) {
            Schema::table('menu', function (Blueprint $t) {
                $t->dropColumn('track_stock');
            });
        }
    }

    public function down(): void
    {
        
    }
};
