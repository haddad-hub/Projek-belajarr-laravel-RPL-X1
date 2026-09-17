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
        $columns = [
            'role' => fn (Blueprint $table) => $table->string('role')->default('customer')->after('email'),
            'phone' => fn (Blueprint $table) => $table->string('phone')->nullable()->after('role'),
            'address' => fn (Blueprint $table) => $table->string('address')->nullable()->after('phone'),
            'vehicle' => fn (Blueprint $table) => $table->string('vehicle')->nullable()->after('address'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', $definition);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'address', 'vehicle']);
        });
    }
};
