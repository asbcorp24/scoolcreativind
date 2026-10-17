<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_lessons', function (Blueprint $table) {
            $table->foreignId('teacher_id')
                ->nullable()
                ->after('study_group_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Для старых занятий пытаемся восстановить преподавателя по точному ФИО.
        DB::table('schedule_lessons')
            ->whereNull('teacher_id')
            ->whereNotNull('teacher_name')
            ->orderBy('id')
            ->get(['id','teacher_name'])
            ->each(function ($lesson) {
                $teacherId=DB::table('users')
                    ->where('is_teacher',true)
                    ->where('name',$lesson->teacher_name)
                    ->value('id');

                if($teacherId){
                    DB::table('schedule_lessons')
                        ->where('id',$lesson->id)
                        ->update(['teacher_id'=>$teacherId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('schedule_lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
        });
    }
};
