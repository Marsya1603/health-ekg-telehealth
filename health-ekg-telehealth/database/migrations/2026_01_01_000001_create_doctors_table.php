<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            // Akun login opsional; satu akun dokter hanya menautkan satu profil dokter.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('code', 24)->unique(); // Doctor ID (DOK-00001), dibuat backend.
            $table->string('name');
            $table->string('registration_number', 64)->nullable(); // Nomor registrasi profesional (str)
            $table->string('specialization', 100)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 190)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
