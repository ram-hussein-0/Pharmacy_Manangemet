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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id(); // bigint id PK
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices'); // bigint purchase_invoice_id FK
            $table->foreignId('product_id')->constrained('products'); // bigint product_id FK
            $table->integer('quantity'); // int quantity
            $table->decimal('unit_price', 10, 2); // decimal unit_price
            $table->decimal('total', 10, 2); // decimal total
            $table->string('batch_number'); // string batch_number
            $table->date('expiry_date'); // date expiry_date
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
