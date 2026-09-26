<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('custom_pages')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('menu_title')->nullable();
            $table->string('subtitle')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('show_in_menu')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('custom_page_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_page_id')->constrained()->cascadeOnDelete();
            $table->enum('type',['image','video','audio','file','link'])->default('image');
            $table->string('title')->nullable();
            $table->string('path')->nullable();
            $table->string('url')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_page_media');
        Schema::dropIfExists('custom_pages');
    }
};
