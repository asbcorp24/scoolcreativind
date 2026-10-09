<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lesson_number')->default(1);
            $table->string('title',255);
            $table->longText('content')->nullable();
            $table->longText('homework_description')->nullable();
            $table->unsignedSmallInteger('homework_due_days')->nullable();
            $table->unsignedSmallInteger('homework_max_score')->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['subject_id','sort_order']);
            $table->unique(['subject_id','lesson_number']);
        });

        Schema::table('journal_lessons', function (Blueprint $table) {
            $table->foreignId('subject_lesson_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('subject_lessons')
                ->nullOnDelete();
        });

        Schema::table('homework_assignments', function (Blueprint $table) {
            $table->foreignId('subject_lesson_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('subject_lessons')
                ->nullOnDelete();

            $table->foreignId('journal_lesson_id')
                ->nullable()
                ->after('subject_lesson_id')
                ->constrained('journal_lessons')
                ->cascadeOnDelete();

            $table->unique(
                ['journal_lesson_id'],
                'homework_journal_lesson_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            $table->dropUnique('homework_journal_lesson_unique');
            $table->dropConstrainedForeignId('journal_lesson_id');
            $table->dropConstrainedForeignId('subject_lesson_id');
        });

        Schema::table('journal_lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_lesson_id');
        });

        Schema::dropIfExists('subject_lessons');
    }
};
