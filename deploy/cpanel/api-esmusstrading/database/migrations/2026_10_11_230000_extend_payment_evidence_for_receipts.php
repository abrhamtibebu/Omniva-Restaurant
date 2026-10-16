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
        Schema::table('payment_evidence', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            $table->unsignedBigInteger('uploaded_by')->nullable()->after('branch_id');
            $table->string('file_path')->nullable()->after('uploaded_by');
            $table->string('file_hash', 64)->nullable()->after('file_path');
            $table->string('payee_name')->nullable()->after('reference');
            $table->string('account_number')->nullable()->after('payee_name');
            $table->string('phone')->nullable()->after('account_number');
            $table->string('transaction_reference')->nullable()->after('phone');
            $table->longText('ocr_raw_text')->nullable()->after('transaction_reference');
            $table->string('ocr_status')->default('pending')->after('ocr_raw_text');
        });

        // Backfill branch from the linked reconciliation when present.
        if (Schema::hasTable('reconciliations')) {
            DB::statement('
                UPDATE payment_evidence
                SET branch_id = (
                    SELECT reconciliations.branch_id
                    FROM reconciliations
                    WHERE reconciliations.id = payment_evidence.reconciliation_id
                )
                WHERE reconciliation_id IS NOT NULL
            ');
        }

        Schema::table('payment_evidence', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });

        // Make reconciliation_id optional (SQLite-friendly rebuild of the FK).
        Schema::table('payment_evidence', function (Blueprint $table) {
            $table->dropForeign(['reconciliation_id']);
        });

        Schema::table('payment_evidence', function (Blueprint $table) {
            $table->unsignedBigInteger('reconciliation_id')->nullable()->change();
        });

        Schema::table('payment_evidence', function (Blueprint $table) {
            $table->foreign('reconciliation_id')->references('id')->on('reconciliations')->nullOnDelete();
            $table->unique(['branch_id', 'file_hash']);
            $table->unique(['branch_id', 'transaction_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_evidence', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'file_hash']);
            $table->dropUnique(['branch_id', 'transaction_reference']);
            $table->dropForeign(['reconciliation_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn([
                'branch_id', 'uploaded_by', 'file_path', 'file_hash',
                'payee_name', 'account_number', 'phone', 'transaction_reference',
                'ocr_raw_text', 'ocr_status',
            ]);
        });

        Schema::table('payment_evidence', function (Blueprint $table) {
            $table->foreign('reconciliation_id')->references('id')->on('reconciliations')->cascadeOnDelete();
        });
    }
};
