<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum: inclui status de estorno
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','completed','failed','cancelled','refunded') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'refund_receipt_path')) {
                $table->string('refund_receipt_path')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('payments', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('refund_receipt_path');
            }
            if (! Schema::hasColumn('payments', 'refund_note')) {
                $table->string('refund_note', 255)->nullable()->after('refunded_at');
            }
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Move refunded back to failed before shrinking enum
            DB::table('payments')->where('status', 'refunded')->update(['status' => 'failed']);
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('payments', function (Blueprint $table) {
            foreach (['refund_receipt_path', 'refunded_at', 'refund_note'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
