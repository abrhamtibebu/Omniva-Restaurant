<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->string('status')->default('available');
            $table->boolean('active')->default(true);
            $table->foreignId('assigned_waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('current_order_id')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
