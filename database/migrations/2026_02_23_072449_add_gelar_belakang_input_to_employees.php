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
            // Store input with quotes/preserve markers for re-editing
            $table->string('gelar_belakang_input')->nullable()->after('gelar_belakang')->comment('Input gelar dengan kutip untuk preserve case (e.g. S."IP")');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('gelar_belakang_input');
        });
    }
};
