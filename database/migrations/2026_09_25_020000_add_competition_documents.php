<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->json('required_documents_json')->nullable()->after('description');
        });

        Schema::create('competition_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_registration_id')->constrained('competition_registrations')->cascadeOnDelete();
            $table->string('document_key',120);
            $table->string('document_label');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->unique(['competition_registration_id','document_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_documents');

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('required_documents_json');
        });
    }
};
