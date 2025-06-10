<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('second_mysql')->create('for_ttn', function (Blueprint $table) {
            $table->id();
            $table->integer('api_service_id');
            $table->integer('id_warehouse');
            $table->string('delivery_method', 100);
            $table->string('order_source', 20);
            $table->integer('order_source_id');
            $table->string('order_source_name', 150);
            $table->string('courier_code', 20);
            $table->integer('account_id');
            $table->json('info_account')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('second_mysql')->dropIfExists('for_ttn');
    }
};
