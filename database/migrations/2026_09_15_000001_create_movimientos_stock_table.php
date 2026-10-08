<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->increments('idmovimiento');
            $table->unsignedInteger('idproducto');
            $table->unsignedInteger('idsucursalorigen')->nullable();
            $table->unsignedInteger('idsucursaldestino')->nullable();
            $table->unsignedBigInteger('idusuario');
            $table->string('tipo', 20);
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('stockorigenantes')->nullable();
            $table->unsignedInteger('stockorigendespues')->nullable();
            $table->unsignedInteger('stockdestinoantes')->nullable();
            $table->unsignedInteger('stockdestinodespues')->nullable();
            $table->timestamps();

            $table->foreign('idproducto')
                ->references('idproducto')
                ->on('productos');
            $table->foreign('idsucursalorigen')
                ->references('idsucursal')
                ->on('sucursales');
            $table->foreign('idsucursaldestino')
                ->references('idsucursal')
                ->on('sucursales');
            $table->foreign('idusuario')
                ->references('id')
                ->on('users');

            $table->index(['idproducto', 'created_at'], 'idx_mov_stock_producto_fecha');
            $table->index(['idsucursalorigen', 'created_at'], 'idx_mov_stock_origen_fecha');
            $table->index(['idsucursaldestino', 'created_at'], 'idx_mov_stock_destino_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
