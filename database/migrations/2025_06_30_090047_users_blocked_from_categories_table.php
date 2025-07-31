<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use Schema::create to create the table
        Schema::create('users_blocked_from_categories', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreignUuid('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->timestamps();
            $table->primary(['user_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_blocked_from_categories');
    }
};