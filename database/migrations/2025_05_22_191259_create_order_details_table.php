<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->unsigned()->unique();
            $table->string('currency', 10)->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->string('payment_method_cod', 10)->nullable();
            $table->decimal('payment_done', 10, 2)->nullable();
            $table->string('delivery_method', 255)->nullable();
            $table->decimal('delivery_price', 10, 2)->nullable();
            $table->string('delivery_package_module', 255)->nullable();
            $table->string('delivery_package_nr', 255)->nullable();
            $table->string('delivery_fullname', 255)->nullable();
            $table->string('delivery_company', 255)->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->string('delivery_city', 255)->nullable();
            $table->string('delivery_state', 255)->nullable();
            $table->string('delivery_postcode', 20)->nullable();
            $table->string('delivery_country_code', 10)->nullable();
            $table->string('delivery_point_id', 100)->nullable();
            $table->string('delivery_point_name', 255)->nullable();
            $table->string('delivery_point_address', 255)->nullable();
            $table->string('delivery_point_postcode', 20)->nullable();
            $table->string('delivery_point_city', 255)->nullable();
            $table->string('invoice_fullname', 255)->nullable();
            $table->string('invoice_company', 255)->nullable();
            $table->string('invoice_nip', 50)->nullable();
            $table->string('invoice_address', 255)->nullable();
            $table->string('invoice_city', 255)->nullable();
            $table->string('invoice_state', 255)->nullable();
            $table->string('invoice_postcode', 20)->nullable();
            $table->string('invoice_country_code', 10)->nullable();
            $table->string('delivery_country', 255)->nullable();
            $table->string('invoice_country', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
