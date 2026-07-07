<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('app_name', 50);
            $table->string('endpoint', 200);
            $table->string('ip_address', 45);
            $table->string('request_source', 100)->nullable();
            $table->smallInteger('response_code');
            $table->unsignedSmallInteger('response_time_ms')->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['app_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_access_logs');
    }
};
