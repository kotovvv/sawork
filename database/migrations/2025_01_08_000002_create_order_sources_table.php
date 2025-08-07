<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
    // Используем основное соединение (MSSQL)
    Schema::create('order_sources', function (Blueprint $table) {
      $table->id();
      $table->string('order_id'); // Can be string for external references
      $table->bigInteger('warehouse_id'); // Используем bigInteger вместо unsignedBigInteger
      $table->string('source_type', 50)->default('manual'); // Ограничиваем размер для MSSQL
      $table->string('source_reference')->nullable();
      $table->bigInteger('api_client_id')->nullable();
      $table->string('external_order_id')->nullable();
      $table->text('source_data')->nullable(); // Используем TEXT вместо JSON для MSSQL
      $table->timestamps();

      $table->index(['order_id', 'warehouse_id']);
      $table->index(['source_type', 'warehouse_id']);
      $table->index('api_client_id');
      $table->index('external_order_id');

      // Для MSSQL не используем foreign key constraints, так как они могут создавать проблемы
      // $table->foreign('api_client_id')->references('id')->on('api_clients')->onDelete('set null');
    });
  }

  public function down()
  {
    Schema::dropIfExists('order_sources');
  }
};
