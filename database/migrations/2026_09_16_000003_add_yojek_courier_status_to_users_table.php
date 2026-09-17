<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->after('vehicle'));
        }
        if (! Schema::hasColumn('users', 'admin_disabled')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('admin_disabled')->default(false)->after('is_active'));
        }
        if (! Schema::hasColumn('users', 'attendance_photo')) {
            Schema::table('users', fn (Blueprint $table) => $table->longText('attendance_photo')->nullable()->after('admin_disabled'));
        }
        if (! Schema::hasColumn('users', 'attendance_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('attendance_at')->nullable()->after('attendance_photo'));
        }
        if (! Schema::hasColumn('users', 'attendance_location')) {
            Schema::table('users', fn (Blueprint $table) => $table->json('attendance_location')->nullable()->after('attendance_at'));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'admin_disabled', 'attendance_photo', 'attendance_at', 'attendance_location']);
        });
    }
};
