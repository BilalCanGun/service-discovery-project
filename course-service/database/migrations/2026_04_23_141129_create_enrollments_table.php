<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('student_number'); // Öğrenci servisiyle eşleşecek numara
            $table->unsignedBigInteger('course_id'); // Kendi DB'mizdeki kursun ID'si
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamps();

            // Foreign key tanımlaması
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};