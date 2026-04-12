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
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'nametag_status')) {
                // Add without positioning to avoid dependency on other columns
                $table->string('nametag_status')->default('none')->nullable(false);
            }
            if (!Schema::hasColumn('employees', 'nametag_generated_at')) {
                $table->timestamp('nametag_generated_at')->nullable();
            }
            if (!Schema::hasColumn('employees', 'nametag_error')) {
                $table->text('nametag_error')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'nametag_error')) {
                $table->dropColumn('nametag_error');
            }
            if (Schema::hasColumn('employees', 'nametag_generated_at')) {
                $table->dropColumn('nametag_generated_at');
            }
            if (Schema::hasColumn('employees', 'nametag_status')) {
                $table->dropColumn('nametag_status');
            }
        });
    }
};
