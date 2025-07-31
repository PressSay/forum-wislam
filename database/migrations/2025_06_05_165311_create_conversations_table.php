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
        Schema::create('conversations', function (Blueprint $table) {
            $table->Uuid('conversation_id');
            $table->timestamps();
            $table->primary('conversation_id');
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreignUuid('conversation_id')->references('conversation_id')->on('conversations')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
    }
};
