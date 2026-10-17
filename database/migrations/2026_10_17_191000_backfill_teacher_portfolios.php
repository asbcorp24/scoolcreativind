<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $teacherIds=DB::table('users')
            ->where('is_teacher',true)
            ->pluck('id')
            ->merge(
                DB::table('study_group_user')
                    ->where('role','teacher')
                    ->pluck('user_id')
            )
            ->unique()
            ->values();

        foreach($teacherIds as $userId){
            if(DB::table('teacher_profiles')->where('user_id',$userId)->exists()) continue;

            $user=DB::table('users')->where('id',$userId)->first();
            if(!$user) continue;

            $slug=Str::slug($user->name) ?: 'teacher-'.$userId;
            if(DB::table('teacher_profiles')->where('slug',$slug)->exists()){
                $slug.='-'.$userId;
            }

            DB::table('teacher_profiles')->insert([
                'user_id'=>$userId,
                'studio_id'=>null,
                'slug'=>$slug,
                'headline'=>'Преподаватель ШКИ',
                'is_public'=>false,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        }

        $members=DB::table('team_members')->whereNull('user_id')->get();

        foreach($members as $member){
            $user=null;

            if(!empty($member->email)){
                $user=DB::table('users')
                    ->whereIn('id',$teacherIds)
                    ->where('email',$member->email)
                    ->first();
            }

            if(!$user){
                $user=DB::table('users')
                    ->whereIn('id',$teacherIds)
                    ->where('name',$member->name)
                    ->first();
            }

            if($user){
                DB::table('team_members')
                    ->where('id',$member->id)
                    ->update(['user_id'=>$user->id]);
            }
        }
    }

    public function down(): void
    {
        // Профили могут быть уже отредактированы пользователями,
        // поэтому откат намеренно не удаляет созданные данные.
    }
};
