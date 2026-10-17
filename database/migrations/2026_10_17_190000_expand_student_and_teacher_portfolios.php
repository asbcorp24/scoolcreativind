<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('studio_id')->nullable()->constrained('studios')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('avatar')->nullable();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->text('education')->nullable();
            $table->text('experience')->nullable();
            $table->text('competencies')->nullable();
            $table->text('courses')->nullable();
            $table->text('publications')->nullable();
            $table->text('professional_achievements')->nullable();
            $table->string('website_url',2000)->nullable();
            $table->string('vk_url',2000)->nullable();
            $table->string('telegram_url',2000)->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('teacher_portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained('teacher_profiles')->cascadeOnDelete();
            $table->foreignId('studio_id')->nullable()->constrained('studios')->nullOnDelete();
            $table->string('title');
            $table->string('type',80)->default('project');
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->string('project_url',2000)->nullable();
            $table->string('video_url',2000)->nullable();
            $table->date('completed_at')->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_public')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('headline')->nullable()->after('class_name');
            $table->text('skills')->nullable()->after('bio');
        });

        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->foreignId('subject_id')
                ->nullable()
                ->after('journal_lesson_id')
                ->constrained('subjects')
                ->nullOnDelete();

            $table->foreignId('mentor_teacher_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('homework_submission_id')
                ->nullable()
                ->after('mentor_teacher_id')
                ->constrained('homework_submissions')
                ->nullOnDelete();
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('homework_submission_id');
            $table->dropConstrainedForeignId('mentor_teacher_id');
            $table->dropConstrainedForeignId('subject_id');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn(['headline','skills']);
        });

        Schema::dropIfExists('teacher_portfolio_items');
        Schema::dropIfExists('teacher_profiles');
    }
};
