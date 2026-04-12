<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('nametag_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('opd_id')->nullable()->index();
            $table->string('opd_unit_id')->nullable();
            $table->json('employee_ids')->nullable();
            $table->integer('total')->default(0);
            $table->integer('done')->default(0);
            $table->integer('fail')->default(0);
            $table->integer('skipped')->default(0);
            $table->string('status', 32)->default('queued')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('nametag_batches');
    }
};
