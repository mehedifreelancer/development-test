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
        Schema::create('tbl_product_data', function (Blueprint $table) {
            $table->increments('id');
            $table->string('product_name', 50);
            $table->string('product_desc', 255);
            $table->string('product_code', 10)->unique();
            $table->decimal('price', 8, 2); 
            $table->unsignedInteger('stock');
            $table->dateTime('added')->nullable();
            $table->dateTime('discontinued')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_product_data');
    }
};
