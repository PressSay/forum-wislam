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
        Schema::create('threads', function (Blueprint $table) {
            $table->uuid('thread_id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->foreignUuid('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignUuid('category_id')->constrained('categories', 'category_id')->onDelete('cascade');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });

        Schema::create('follow_threads', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreignUuid('thread_id')->references('thread_id')->on('threads')->onDelete('cascade');
            $table->primary(['user_id', 'thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_threads');
        Schema::dropIfExists('threads');
    }
};
