<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->string('currency', 8)->default('ETB');
            $table->string('timezone')->default('Africa/Addis_Ababa');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->string('currency', 8)->default('ETB');
            $table->string('timezone')->default('Africa/Addis_Ababa');
            $table->decimal('tax_rate', 8, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('period');
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['branch_id', 'key', 'period']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('role_id')->nullable()->after('phone')->constrained();
            $table->foreignId('branch_id')->nullable()->after('role_id')->constrained();
            $table->boolean('active')->default(true)->after('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['phone', 'active']);
        });

        Schema::dropIfExists('sequences');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('restaurants');
        Schema::dropIfExists('roles');
    }
};
