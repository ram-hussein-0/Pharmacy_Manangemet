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
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // bigint id PK
            $table->foreignId('category_id')->constrained('categories'); // bigint category_id FK
            $table->string('name'); // string name
            $table->string('barcode')->unique(); // string barcode
            $table->string('description')->nullable(); // string description
            $table->decimal('sale_price', 10, 2); // decimal sale_price
            $table->integer('minimum_stock')->default(0); // int minimum_stock
            $table->boolean('is_active')->default(true); // boolean is_active
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
