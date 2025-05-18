<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('ai_interactions', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
      $table->string('provider');
      $table->string('type'); // 'generate', 'chat', 'embeddings'
      $table->text('input');
      $table->longText('output')->nullable();
      $table->json('options')->nullable();
      $table->integer('tokens_used')->default(0);
      $table->float('duration')->default(0); // in seconds
      $table->boolean('success')->default(true);
      $table->text('error')->nullable();
      $table->timestamps();

      $table->index('provider');
      $table->index('type');
      $table->index('created_at');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('ai_interactions');
  }
};
