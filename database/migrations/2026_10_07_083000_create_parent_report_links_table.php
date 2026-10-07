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
            $table->string('target_type',20)->default('student');
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('study_group_id')->nullable()->constrained('study_groups')->cascadeOnDelete();
            $table->string('token',96)->unique();
            $table->date('report_until');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->index(['target_type','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_report_links');
    }
};
