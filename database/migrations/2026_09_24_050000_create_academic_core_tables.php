<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('study_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('study_year')->default(1);
            $table->string('code')->unique();
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('curator_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('study_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role',['student','teacher']);
            $table->timestamps();
            $table->unique(['study_group_id','user_id','role']);
        });

        Schema::table('schedule_lessons', function (Blueprint $table) {
            $table->foreignId('study_group_id')->nullable()->after('studio_id')->constrained()->nullOnDelete();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description',1000)->nullable();
            $table->timestamps();
        });

        Schema::create('group_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['study_group_id','subject_id']);
        });

        Schema::create('journal_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('lesson_date');
            $table->string('topic');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('attendance',['present','absent','late','excused'])->default('present');
            $table->decimal('grade',4,2)->nullable();
            $table->string('grade_label')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['journal_lesson_id','student_id']);
        });

        Schema::create('homework_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('attachment')->nullable();
            $table->text('external_url')->nullable();
            $table->unsignedSmallInteger('max_score')->default(5);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('text_answer')->nullable();
            $table->string('file')->nullable();
            $table->text('external_url')->nullable();
            $table->enum('status',['draft','submitted','reviewed','returned'])->default('submitted');
            $table->decimal('score',6,2)->nullable();
            $table->text('teacher_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['homework_assignment_id','student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homework_assignments');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journal_lessons');
        Schema::dropIfExists('group_subjects');
        Schema::dropIfExists('subjects');
        Schema::table('schedule_lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_group_id');
        });
        Schema::dropIfExists('study_group_user');
        Schema::dropIfExists('study_groups');
    }
};
