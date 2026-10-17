<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_lessons',function(Blueprint $table){
            $table->foreignId('substitute_teacher_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('substitution_note',500)->nullable()->after('substitute_teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_lessons',function(Blueprint $table){
            $table->dropConstrainedForeignId('substitute_teacher_id');
            $table->dropColumn('substitution_note');
        });
    }
};
