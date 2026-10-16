<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'type']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'stock_location_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->string('type');
            $table->decimal('quantity', 15, 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bono_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('menu_category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_value', 15, 2)->default(0);
            $table->string('redemption_department')->default('any');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('bono_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bono_type_id')->constrained()->cascadeOnDelete();
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id')->default(0);
            $table->decimal('quantity', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'bono_type_id', 'holder_type', 'holder_id'], 'bono_balance_holder_unique');
        });

        Schema::create('bono_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bono_type_id')->constrained()->restrictOnDelete();
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id')->default(0);
            $table->string('direction');
            $table->decimal('quantity', 15, 3);
            $table->string('movement_type');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'bono_type_id', 'created_at']);
        });

        Schema::create('department_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('department');
            $table->date('business_date');
            $table->string('status')->default('open');
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'user_id', 'status']);
        });

        Schema::create('bono_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('kind');
            $table->foreignId('issuer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bono_handover_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bono_handover_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bono_type_id')->constrained()->restrictOnDelete();
            $table->decimal('requested_quantity', 15, 3)->default(0);
            $table->decimal('approved_quantity', 15, 3)->default(0);
            $table->decimal('issued_quantity', 15, 3)->default(0);
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('source_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('department_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('requested_quantity', 15, 3)->default(0);
            $table->decimal('approved_quantity', 15, 3)->default(0);
            $table->decimal('issued_quantity', 15, 3)->default(0);
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('department');
            $table->foreignId('department_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->decimal('expected_value', 15, 2)->default(0);
            $table->decimal('submitted_cash', 15, 2)->default(0);
            $table->decimal('verified_cash', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained()->cascadeOnDelete();
            $table->string('line_kind');
            $table->foreignId('bono_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('issued_quantity', 15, 3)->default(0);
            $table->decimal('sold_quantity', 15, 3)->default(0);
            $table->decimal('returned_quantity', 15, 3)->default(0);
            $table->decimal('unaccounted_quantity', 15, 3)->default(0);
            $table->decimal('expected_quantity', 15, 3)->default(0);
            $table->decimal('physical_quantity', 15, 3)->nullable();
            $table->decimal('verified_quantity', 15, 3)->nullable();
            $table->decimal('expected_value', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payment_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->string('verification_status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('discrepancy_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('expected_quantity', 15, 3)->nullable();
            $table->decimal('actual_quantity', 15, 3)->nullable();
            $table->decimal('variance_quantity', 15, 3)->nullable();
            $table->decimal('expected_value', 15, 2)->nullable();
            $table->decimal('actual_value', 15, 2)->nullable();
            $table->text('reason')->nullable();
            $table->text('resolution')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('bar_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barman_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('bono_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_session_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });

        Schema::create('department_sales_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_session_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date');
            $table->string('department');
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name_snapshot');
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('value_snapshot', 15, 2);
            $table->string('source')->default('manual');
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_session_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('expected_quantity', 15, 3);
            $table->decimal('physical_quantity', 15, 3);
            $table->decimal('variance_quantity', 15, 3);
            $table->timestamps();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->after('payment_status');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('accepted_quantity', 15, 3)->nullable()->after('quantity');
            $table->decimal('rejected_quantity', 15, 3)->default(0)->after('accepted_quantity');
        });

        $now = now();
        DB::table('roles')->updateOrInsert(
            ['slug' => 'barman'],
            ['name' => 'Barman', 'created_at' => $now, 'updated_at' => $now],
        );
        DB::table('roles')->updateOrInsert(
            ['slug' => 'storekeeper'],
            ['name' => 'Storekeeper', 'created_at' => $now, 'updated_at' => $now],
        );

        $branches = DB::table('branches')->pluck('id');
        foreach ($branches as $branchId) {
            foreach (['store' => 'Store', 'bar' => 'Bar', 'kitchen' => 'Kitchen'] as $type => $name) {
                $locationId = DB::table('stock_locations')->insertGetId([
                    'branch_id' => $branchId,
                    'name' => $name,
                    'type' => $type,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($type !== 'store') {
                    continue;
                }

                $items = DB::table('inventory_items')->where('branch_id', $branchId)->get(['id', 'quantity_on_hand']);
                foreach ($items as $item) {
                    DB::table('stock_balances')->insert([
                        'inventory_item_id' => $item->id,
                        'stock_location_id' => $locationId,
                        'quantity' => $item->quantity_on_hand,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['accepted_quantity', 'rejected_quantity']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approved_at', 'approval_notes']);
        });

        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('department_sales_summaries');
        Schema::dropIfExists('bar_redemptions');
        Schema::dropIfExists('discrepancy_cases');
        Schema::dropIfExists('payment_evidence');
        Schema::dropIfExists('reconciliation_lines');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('stock_request_lines');
        Schema::dropIfExists('stock_requests');
        Schema::dropIfExists('bono_handover_lines');
        Schema::dropIfExists('bono_handovers');
        Schema::dropIfExists('department_sessions');
        Schema::dropIfExists('bono_ledger_entries');
        Schema::dropIfExists('bono_balances');
        Schema::dropIfExists('bono_types');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_locations');

        DB::table('roles')->whereIn('slug', ['barman', 'storekeeper'])->delete();
    }
};
