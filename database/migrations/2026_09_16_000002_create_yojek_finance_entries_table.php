<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id', 24)->nullable();
            $table->string('category');
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'created_at']);
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entries');
    }
};
