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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id(); // bigint id PK
            $table->foreignId('sale_invoice_id')->constrained('sale_invoices'); // bigint sale_invoice_id FK
            $table->foreignId('product_id')->constrained('products'); // bigint product_id FK
            $table->foreignId('product_batch_id')->constrained('product_batches'); // bigint product_batch_id FK
            $table->integer('quantity'); // int quantity
            $table->decimal('unit_price', 10, 2); // decimal unit_price
            $table->decimal('purchase_price_at_sale', 10, 2); // decimal purchase_price_at_sale
            $table->decimal('total', 10, 2); // decimal total
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
