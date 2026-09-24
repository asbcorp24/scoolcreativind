<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('class_name')->nullable();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->string('portfolio_slug')->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('schedule_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('teacher_name')->nullable();
            $table->date('lesson_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room')->nullable();
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('project');
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->text('project_url')->nullable();
            $table->text('video_url')->nullable();
            $table->date('completed_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('organizer')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->text('url')->nullable();
            $table->string('cover')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('result')->nullable();
            $table->string('level')->nullable();
            $table->date('awarded_at')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('competitions');
        Schema::dropIfExists('portfolio_items');
        Schema::dropIfExists('schedule_lessons');
        Schema::dropIfExists('student_profiles');
    }
};
