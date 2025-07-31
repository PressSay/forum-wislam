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
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('tag_id')->primary();
            $table->string("name");
            $table->string("description");
            $table->string("slug")->unique();
            $table->timestamps();
        });

        Schema::create('thread_tag', function (Blueprint $table) {
            $table->foreignUuid("thread_id")->constrained('threads', 'thread_id')->onDelete('cascade');
            $table->foreignUuid('tag_id')->constrained('tags', 'tag_id')->onDelete('cascade');
            $table->primary(["thread_id", "tag_id"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_tag');
        Schema::dropIfExists('tags');
    }
};
