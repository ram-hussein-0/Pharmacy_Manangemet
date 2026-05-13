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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id(); // bigint id PK
            $table->foreignId('product_id')->constrained('products'); // bigint product_id FK
            $table->foreignId('created_by')->constrained('users'); // bigint created_by FK
            $table->string('type'); // string type (In/Out)
            $table->integer('quantity'); // int quantity
            $table->string('reference_type'); // string reference_type (Sale/Purchase)
            $table->string('reference_id')->nullable(); // string reference_id
            $table->string('notes')->nullable(); // string notes
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
