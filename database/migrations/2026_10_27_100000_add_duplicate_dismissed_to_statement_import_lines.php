<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // duplicate_dismissed: the user said "not a duplicate", the line stays out of the check;
    // unselected_as_duplicate: unselected by the check (not by the user), selected again when no longer a duplicate
    public function up(): void
    {
        foreach (['duplicate_dismissed', 'unselected_as_duplicate'] as $column) {
            if (! Schema::hasColumn('statement_import_lines', $column)) {
                Schema::table('statement_import_lines', function (Blueprint $table) use ($column) {
                    $table->boolean($column)->default(false)->after('duplicate_movement_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['duplicate_dismissed', 'unselected_as_duplicate'] as $column) {
            if (Schema::hasColumn('statement_import_lines', $column)) {
                Schema::table('statement_import_lines', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
