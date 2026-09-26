<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperation_items', function (Blueprint $table) {
            $table->id();
            $table->enum('type',['proposal','partner','project']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('cooperation_applications', function (Blueprint $table) {
            $table->id();
            $table->enum('role',['partner','curator','teacher','other']);
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('message')->nullable();
            $table->enum('status',['new','processing','accepted','rejected'])->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperation_applications');
        Schema::dropIfExists('cooperation_items');
    }
};
