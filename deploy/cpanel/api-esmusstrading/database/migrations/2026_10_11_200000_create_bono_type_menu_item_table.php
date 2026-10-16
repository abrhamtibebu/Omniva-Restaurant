<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bono_type_menu_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bono_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bono_type_id', 'menu_item_id']);
        });

        // Carry over any legacy single-item links.
        $rows = DB::table('bono_types')
            ->whereNotNull('menu_item_id')
            ->get(['id', 'menu_item_id']);

        foreach ($rows as $row) {
            DB::table('bono_type_menu_item')->insert([
                'bono_type_id' => $row->id,
                'menu_item_id' => $row->menu_item_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bono_type_menu_item');
    }
};
