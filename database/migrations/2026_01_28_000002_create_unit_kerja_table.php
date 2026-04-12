<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('unit_kerja')) {
            return;
        }

        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('opd_id')->nullable()->index();
            $table->string('code', 50)->nullable();
            $table->string('nama', 150);
            $table->string('status', 20)->default('AKTIF');
            $table->string('alamat')->nullable();
            $table->string('kecamatan')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerja');
    }
};
