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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id(); // bigint id PK
            $table->foreignId('created_by')->constrained('users'); // bigint created_by FK
            $table->string('title'); // string title
            $table->string('type'); // string type
            $table->decimal('amount', 10, 2); // decimal amount
            $table->date('expense_date'); // date expense_date
            $table->string('notes')->nullable(); // string notes
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
