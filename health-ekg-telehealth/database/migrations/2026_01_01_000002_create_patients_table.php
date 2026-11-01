<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            // Akun login opsional untuk pasien.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('code', 24)->unique(); // Patient ID (PST-00001), dibuat backend.
            $table->string('medical_record_number', 64)->unique(); // Nomor rekam medis (unik)
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['L', 'P'])->nullable(); // L = Laki-laki, P = Perempuan
            $table->string('phone', 32)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->date('registered_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['doctor_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
