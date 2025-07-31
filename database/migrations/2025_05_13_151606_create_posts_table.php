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
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('post_id');
            $table->primary('post_id');
            $table->longText('content');
            $table->foreignUuid('user_id')->references( 'user_id')->on('users')->onDelete('cascade');
            $table->foreignUuid('thread_id')->references( 'thread_id')->on('threads')->onDelete('cascade');
            $table->foreignUuid('parrent_id')->nullable()->references('post_id')->on('posts')->onDelete('cascade'); // Nested Answers
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
