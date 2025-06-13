<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('second_mysql')->table('courier_forms', function (Blueprint $table) {
            $table->json('default_values')->nullable()->after('form');
        });
    }

    public function down(): void
    {
        Schema::connection('second_mysql')->table('courier_forms', function (Blueprint $table) {
            $table->dropColumn('default_values');
        });
    }
};
