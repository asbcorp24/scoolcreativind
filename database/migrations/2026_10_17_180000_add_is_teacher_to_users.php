<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_teacher')->default(false)->after('is_admin')->index();
        });

        // Все преподаватели, уже привязанные к группе.
        DB::table('users')
            ->whereIn('id', function ($query) {
                $query->select('user_id')
                    ->from('study_group_user')
                    ->where('role', 'teacher');
            })
            ->update(['is_teacher' => true]);

        // Все преподаватели, уже назначенные на предмет.
        DB::table('users')
            ->whereIn('id', function ($query) {
                $query->select('teacher_id')
                    ->from('group_subjects')
                    ->whereNotNull('teacher_id');
            })
            ->update(['is_teacher' => true]);

        // Восстанавливаем старые аккаунты, созданные через раздел преподавателей,
        // но оставшиеся без группы/предмета. Самостоятельно зарегистрированные
        // пользователи имеют admission_application, ученики состоят в группе
        // с ролью student, администраторы исключаются отдельно.
        DB::table('users')
            ->where('is_admin', false)
            ->whereNull('admin_sections')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('study_group_user')
                    ->whereColumn('study_group_user.user_id', 'users.id')
                    ->where('study_group_user.role', 'student');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('admission_applications')
                    ->whereColumn('admission_applications.user_id', 'users.id');
            })
            ->update(['is_teacher' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_teacher']);
            $table->dropColumn('is_teacher');
        });
    }
};
