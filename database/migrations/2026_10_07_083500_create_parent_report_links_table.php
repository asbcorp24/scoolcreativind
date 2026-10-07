<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_report_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->string('token',80)->unique();
            $table->date('report_until');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->index(['student_profile_id','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_report_links');
    }
};
