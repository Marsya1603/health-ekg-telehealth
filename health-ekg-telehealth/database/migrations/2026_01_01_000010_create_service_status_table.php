<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_status', function (Blueprint $table) {
            $table->id();
            $table->string('service', 32)->unique(); // mqtt_worker | scheduler | mqtt_broker | database
            $table->string('status', 16)->nullable(); // aktif | error | unknown
            $table->text('detail')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_status');
    }
};
