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
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id(); // bigint id PK
            $table->foreignId('supplier_id')->constrained('suppliers'); // bigint supplier_id FK
            $table->foreignId('created_by')->constrained('users'); // bigint created_by FK
            $table->string('invoice_number')->unique(); // string invoice_number
            $table->date('invoice_date'); // date invoice_date
            $table->decimal('subtotal', 10, 2); // decimal subtotal
            $table->decimal('discount', 10, 2)->default(0); // decimal discount
            $table->decimal('tax', 10, 2)->default(0); // decimal tax
            $table->decimal('total', 10, 2); // decimal total
            $table->string('status'); // string status
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
