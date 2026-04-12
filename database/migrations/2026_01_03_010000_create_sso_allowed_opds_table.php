<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sso_allowed_opds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('app_code', 100)->index();
            $table->unsignedBigInteger('opd_id')->index();
            $table->timestamps();

            $table->unique(['user_id', 'app_code', 'opd_id'], 'sso_allowed_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_allowed_opds');
    }
};
