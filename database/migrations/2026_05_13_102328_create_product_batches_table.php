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
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id(); // bigint id PK
            $table->foreignId('product_id')->constrained('products'); // bigint product_id FK
            $table->foreignId('purchase_item_id')->constrained('purchase_items'); // bigint purchase_item_id FK
            $table->string('batch_number'); // string batch_number
            $table->date('expiry_date'); // date expiry_date
            $table->integer('quantity'); // int quantity
            $table->decimal('purchase_price', 10, 2); // decimal purchase_price
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
