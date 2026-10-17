<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_documents', function (Blueprint $table) {
            $table->string('item_type', 30)->default('document')->after('title');
            $table->text('external_url')->nullable()->after('file_size');
            $table->string('button_label', 80)->nullable()->after('external_url');
        });
    }

    public function down(): void
    {
        Schema::table('official_documents', function (Blueprint $table) {
            $table->dropColumn(['item_type','external_url','button_label']);
        });
    }
};
