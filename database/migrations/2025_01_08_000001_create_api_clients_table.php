<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Используем основное соединение (MSSQL)
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_key', 32)->unique();
            $table->string('api_secret', 64);
            $table->text('warehouse_ids')->nullable(); // Используем TEXT вместо JSON для MSSQL
            $table->text('permissions')->nullable(); // Используем TEXT вместо JSON для MSSQL
            $table->bit('is_active')->default(1); // Используем BIT вместо BOOLEAN для MSSQL
            $table->integer('rate_limit')->default(1000);
            $table->datetime('last_used_at')->nullable();
            $table->text('ip_whitelist')->nullable(); // Используем TEXT вместо JSON для MSSQL
            $table->string('webhook_url', 500)->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['api_key', 'is_active']);
            $table->index('last_used_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('api_clients');
    }
};
