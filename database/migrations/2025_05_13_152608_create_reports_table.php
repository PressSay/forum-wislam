<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\Status;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('thread_id');
            $table->string('reason');
            $table->enum('status', [Status::PENDING->value, Status::APPROVED->value, Status::REJECTED->value])->default(Status::PENDING->value);
            $table->timestamps();

            // Định nghĩa khóa chính
            $table->primary(['user_id', 'thread_id']);

            // Ràng buộc khóa ngoại với ON DELETE CASCADE
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('thread_id')->references('thread_id')->on('threads')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
