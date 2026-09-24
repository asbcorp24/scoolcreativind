<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('quizzes', function(Blueprint $table){
   $table->id();
   $table->string('title');
   $table->text('description')->nullable();
   $table->unsignedTinyInteger('pass_score')->default(70);
   $table->json('questions_json');
   $table->boolean('is_published')->default(false);
   $table->timestamps();
  });

  Schema::create('quiz_attempts', function(Blueprint $table){
   $table->id();
   $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
   $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->unsignedTinyInteger('score')->default(0);
   $table->boolean('passed')->default(false);
   $table->json('answers_json')->nullable();
   $table->string('certificate_code')->nullable()->unique();
   $table->timestamp('completed_at')->nullable();
   $table->timestamps();
  });

  Schema::create('competition_registrations', function(Blueprint $table){
   $table->id();
   $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
   $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->enum('status',['registered','submitted','reviewed','cancelled'])->default('registered');
   $table->text('submission_text')->nullable();
   $table->string('submission_url',2000)->nullable();
   $table->string('file_path')->nullable();
   $table->string('file_name')->nullable();
   $table->timestamp('submitted_at')->nullable();
   $table->timestamps();
   $table->unique(['competition_id','user_id']);
  });
 }

 public function down(): void {
  Schema::dropIfExists('competition_registrations');
  Schema::dropIfExists('quiz_attempts');
  Schema::dropIfExists('quizzes');
 }
};