<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->foreignId('journal_lesson_id')
                ->nullable()
                ->after('student_profile_id')
                ->constrained('journal_lessons')
                ->nullOnDelete();

            $table->string('practical_kind',20)
                ->nullable()
                ->after('journal_lesson_id');

            $table->unique(
                ['student_profile_id','journal_lesson_id'],
                'portfolio_student_lesson_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->dropUnique('portfolio_student_lesson_unique');
            $table->dropConstrainedForeignId('journal_lesson_id');
            $table->dropColumn('practical_kind');
        });
    }
};
