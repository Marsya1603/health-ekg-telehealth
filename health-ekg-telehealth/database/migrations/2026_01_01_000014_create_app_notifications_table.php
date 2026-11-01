<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notifikasi aplikasi (per pengguna), dibuat dari event nyata sistem.
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 24)->default('sistem'); // sesi | perangkat | data | sistem | catatan
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->string('link', 190)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
