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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('category_id');
            $table->primary('category_id');
            $table->foreignUuid('parrent_id')->nullable()->references('category_id')->on('categories')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->timestamps();
        });

        Schema::create('follow_categories', function (Blueprint $table) {
            $table->foreignUuid(column: 'user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreignUuid('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->primary(['user_id', 'category_id']);
            $table->timestamps();
        });

        Schema::create('decentralization_of_categories', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreignUuid('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->primary(['user_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('follow_categories');
        Schema::dropIfExists('decentralization_of_categories');
    }
};
