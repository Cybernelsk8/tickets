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
            $table->index(['area_id', 'created_at'], 'tasks_area_created_at_index');
            $table->index(['created_at'], 'tasks_created_at_index');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->index(['area_id', 'created_at'], 'tickets_area_created_at_index');
            $table->index(['created_at'], 'tickets_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'tasks' => ['tasks_area_created_at_index', 'tasks_created_at_index'],
            'tickets' => ['tickets_area_created_at_index', 'tickets_created_at_index'],
        ] as $tableName => $indexes) {
            foreach ($indexes as $index) {
                if (Schema::hasIndex($tableName, $index)) {
                    Schema::table($tableName, function (Blueprint $table) use ($index): void {
                        $table->dropIndex($index);
                    });
                }
            }
        }
    }
};
