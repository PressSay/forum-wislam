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
        Schema::create('blocked_users', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('user_id')->on('users')->onDelete('cascade'); // Người chặn
            $table->foreignUuid('blocked_user_id')->references('user_id')->on('users')->onDelete('cascade'); // Người bị chặn
            $table->timestamps();
            $table->primary(['user_id', 'blocked_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_users');
    }
};
