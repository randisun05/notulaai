<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * unit_id/assignee_id/created_by sudah ter-index otomatis lewat foreign key.
 * Ditambahkan di sini: kombinasi kolom yang benar-benar dipakai bareng di
 * WHERE/ORDER BY di seluruh app (TaskController, AnalyticsController,
 * DashboardController, TaskExportController) tapi belum pernah ter-index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['unit_id', 'status']);
            $table->index('deadline');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->index(['unit_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['unit_id', 'status']);
            $table->dropIndex(['deadline']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropIndex(['unit_id', 'date']);
            $table->dropIndex(['status']);
        });
    }
};
