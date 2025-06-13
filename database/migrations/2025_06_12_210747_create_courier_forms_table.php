<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('second_mysql')->create('courier_forms', function (Blueprint $table) {
            $table->string('courier_code', 20)->primary();
            $table->json('form');
        });
    }

    public function down(): void
    {
        Schema::connection('second_mysql')->dropIfExists('courier_forms');
    }
};
