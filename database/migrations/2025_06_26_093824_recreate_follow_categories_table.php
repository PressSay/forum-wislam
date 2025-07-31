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
        // Drop bảng nếu nó tồn tại
        Schema::dropIfExists('follow_categories');

        Schema::create('follow_categories', function (Blueprint $table) {
            $table->foreignUuid(column: 'user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreignUuid('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->primary(['user_id', 'category_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_categories');
    }
};
