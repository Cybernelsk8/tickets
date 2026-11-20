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
        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(['status', 'completed_at'], 'tasks_status_completed_at_index');
            $table->index(['area_id', 'status', 'completed_at'], 'tasks_area_status_completed_at_index');
            $table->index(['assignee_id', 'status', 'completed_at'], 'tasks_assignee_status_completed_at_index');
            $table->index(['status', 'position', 'id'], 'tasks_status_position_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'tasks_status_completed_at_index',
            'tasks_area_status_completed_at_index',
            'tasks_assignee_status_completed_at_index',
            'tasks_status_position_id_index',
        ] as $index) {
            if (Schema::hasIndex('tasks', $index)) {
                Schema::table('tasks', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }
    }
};
