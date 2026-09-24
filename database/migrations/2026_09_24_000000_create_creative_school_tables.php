<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('users', function(Blueprint $t){
   $t->id(); $t->string('name'); $t->string('email')->unique(); $t->string('phone')->nullable();
   $t->timestamp('email_verified_at')->nullable(); $t->string('password'); $t->boolean('is_admin')->default(false);
   $t->rememberToken(); $t->timestamps();
  });
  Schema::create('studios', function(Blueprint $t){
   $t->id(); $t->string('title'); $t->string('slug')->unique(); $t->string('subtitle')->nullable();
   $t->text('description')->nullable(); $t->string('icon')->nullable(); $t->string('accent')->nullable();
   $t->string('cover')->nullable(); $t->unsignedInteger('sort_order')->default(0); $t->boolean('is_active')->default(true); $t->timestamps();
  });
  Schema::create('media_items', function(Blueprint $t){
   $t->id(); $t->foreignId('studio_id')->constrained()->cascadeOnDelete(); $t->string('type',32);
   $t->string('title')->nullable(); $t->text('url'); $t->string('thumbnail')->nullable(); $t->text('caption')->nullable(); $t->longText('hotspots_json')->nullable();
   $t->unsignedInteger('sort_order')->default(0); $t->boolean('is_featured')->default(false); $t->timestamps();
  });
  Schema::create('student_projects', function(Blueprint $t){
   $t->id(); $t->foreignId('studio_id')->nullable()->constrained()->nullOnDelete(); $t->string('title'); $t->string('author')->nullable();
   $t->string('year')->nullable(); $t->text('description')->nullable(); $t->string('cover')->nullable(); $t->text('project_url')->nullable();
   $t->boolean('is_featured')->default(false); $t->timestamps();
  });
  Schema::create('news_posts', function(Blueprint $t){
   $t->id(); $t->string('title'); $t->string('slug')->unique(); $t->text('excerpt')->nullable(); $t->longText('body');
   $t->string('cover')->nullable(); $t->timestamp('published_at')->nullable(); $t->boolean('is_published')->default(false); $t->timestamps();
  });
  Schema::create('events', function(Blueprint $t){
   $t->id(); $t->string('title'); $t->string('slug')->unique(); $t->text('description')->nullable(); $t->timestamp('starts_at');
   $t->string('location')->nullable(); $t->string('cover')->nullable(); $t->text('registration_url')->nullable(); $t->boolean('is_published')->default(true); $t->timestamps();
  });
  Schema::create('admission_applications', function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
   $t->string('name'); $t->date('birth_date')->nullable(); $t->string('phone'); $t->string('email')->nullable(); $t->text('message')->nullable();
   $t->enum('status',['new','processing','accepted','rejected'])->default('new'); $t->timestamps();
  });
 }
 public function down(): void {
  Schema::dropIfExists('admission_applications'); Schema::dropIfExists('events'); Schema::dropIfExists('news_posts');
  Schema::dropIfExists('student_projects'); Schema::dropIfExists('media_items'); Schema::dropIfExists('studios'); Schema::dropIfExists('users');
 }
};
