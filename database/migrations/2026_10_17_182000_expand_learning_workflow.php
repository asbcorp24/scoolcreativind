<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false)->index();
            $table->boolean('is_closed')->default(false)->index();
            $table->timestamps();
        });

        $currentYearId=DB::table('academic_years')->insertGetId([
            'name'=>'2026/2027',
            'starts_on'=>'2026-09-01',
            'ends_on'=>'2027-08-31',
            'is_current'=>true,
            'is_closed'=>false,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        Schema::table('study_groups', function (Blueprint $table) {
            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('id')
                ->constrained('academic_years')
                ->nullOnDelete();
        });

        DB::table('study_groups')->whereNull('academic_year_id')->update([
            'academic_year_id'=>$currentYearId,
        ]);

        Schema::table('schedule_lessons', function (Blueprint $table) {
            $table->foreignId('subject_id')
                ->nullable()
                ->after('study_group_id')
                ->constrained('subjects')
                ->nullOnDelete();
            $table->foreignId('subject_lesson_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('subject_lessons')
                ->nullOnDelete();
            $table->foreignId('journal_lesson_id')
                ->nullable()
                ->after('subject_lesson_id')
                ->constrained('journal_lessons')
                ->nullOnDelete();
            $table->string('status', 30)->default('planned')->after('is_published')->index();
            $table->text('status_note')->nullable()->after('status');
            $table->date('original_lesson_date')->nullable()->after('status_note');
            $table->time('original_starts_at')->nullable()->after('original_lesson_date');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('entity_type', 160)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('description', 500)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('study_group_id')->nullable()->constrained('study_groups')->cascadeOnDelete();
            $table->string('audience', 30)->default('all')->index();
            $table->string('title');
            $table->text('body');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('activity_logs');

        Schema::table('schedule_lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_lesson_id');
            $table->dropConstrainedForeignId('subject_lesson_id');
            $table->dropConstrainedForeignId('subject_id');
            $table->dropColumn(['status','status_note','original_lesson_date','original_starts_at']);
        });

        Schema::table('study_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
        });

        Schema::dropIfExists('academic_years');
    }
};
