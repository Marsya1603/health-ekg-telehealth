<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->string('component', 32)->index(); // worker | broker | backend | scheduler | device
            $table->string('event_type', 48);
            $table->string('status', 16)->default('info'); // info | peringatan | error | pulih
            $table->string('error_code', 32)->nullable();
            $table->text('message')->nullable();
            $table->dateTime('occurred_at', 3)->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_events');
    }
};
