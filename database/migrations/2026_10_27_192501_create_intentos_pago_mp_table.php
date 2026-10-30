
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intentos_pago_mp', function (Blueprint $table) {
            $table->id();

            // Identificador único del intento de pago.
            $table->uuid('referencia')->unique();

            // Datos de la operación del local.
            $table->unsignedBigInteger('idusuario');
            $table->unsignedInteger('idsucursal');
            $table->unsignedInteger('idventa')->nullable();

            // Datos de la venta antes de confirmarla.
            $table->json('datos_venta');

            // Importe calculado por Laravel.
            $table->decimal('importe', 12, 2);

            // Estado del cobro.
            $table->string('estado', 30)->default('CREADO');

            // Identificadores de Mercado Pago.
            $table->string('mp_preference_id', 150)
                ->nullable()
                ->unique();

            $table->string('mp_payment_id', 150)
                ->nullable()
                ->unique();

            $table->timestamp('pagado_at')->nullable();
            $table->timestamp('confirmado_at')->nullable();

            $table->timestamps();

            $table->index(['estado', 'created_at']);

            $table->foreign('idventa')
                ->references('idventa')
                ->on('ventas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_pago_mp');
    }
};
