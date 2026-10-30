<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('intentos_pago_mp', function (Blueprint $table) {
            $table->string('mp_order_id', 80)->nullable()->unique();
            $table->uuid('qr_idempotency_key')->nullable()->unique();
            $table->string('qr_external_pos_id', 80)->nullable()->index();
        });
    }
    public function down(): void {
        Schema::table('intentos_pago_mp', function (Blueprint $table) {
            $table->dropUnique(['mp_order_id']);
            $table->dropUnique(['qr_idempotency_key']);
            $table->dropIndex(['qr_external_pos_id']);
            $table->dropColumn(['mp_order_id', 'qr_idempotency_key', 'qr_external_pos_id']);
        });
    }
};
