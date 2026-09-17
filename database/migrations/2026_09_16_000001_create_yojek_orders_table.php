<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->string('id', 24)->primary();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('courier_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_username')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone', 30)->nullable();
            $table->string('item');
            $table->string('category', 50)->default('Makanan');
            $table->unsignedInteger('qty')->default(1);
            $table->string('pickup');
            $table->string('dropoff');
            $table->text('note')->nullable();
            $table->string('status', 32)->default('new')->index();
            $table->decimal('revenue', 12, 2)->default(0);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['customer_user_id', 'status']);
            $table->index(['courier_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
