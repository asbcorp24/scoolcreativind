<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries',function(Blueprint $table){
            $table->text('absence_reason')->nullable()->after('attendance');
            $table->string('absence_document')->nullable()->after('absence_reason');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries',function(Blueprint $table){
            $table->dropColumn(['absence_reason','absence_document']);
        });
    }
};
