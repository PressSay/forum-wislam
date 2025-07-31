<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->uuid('image_id');
            $table->string('url', 2048);
            $table->uuid('thread_id')->nullable();
            $table->uuid('post_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->timestamps();

            // Định nghĩa khóa chính
            $table->primary(['image_id']);

            $table->foreign('thread_id')->references('thread_id')->on('threads')->onDelete('cascade');
            $table->foreign('post_id')->references('post_id')->on('posts')->onDelete('cascade');
            $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('cascade');
        });

        Schema::create('deleted_images', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->timestamps();
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared('
                CREATE TRIGGER after_image_delete
                AFTER DELETE ON images
                FOR EACH ROW
                BEGIN
                    INSERT INTO deleted_images (url, created_at, updated_at)
                    VALUES (OLD.url, NOW(), NOW());
                END;
            ');
        } elseif ($driver === 'pgsql') {
            DB::unprepared('
                CREATE OR REPLACE FUNCTION log_deleted_image()
                RETURNS TRIGGER AS $$
                BEGIN
                    INSERT INTO deleted_images (url, created_at, updated_at)
                    VALUES (OLD.url, NOW(), NOW());
                    RETURN OLD;
                END;
                $$ LANGUAGE plpgsql;
            ');

            DB::unprepared('
                CREATE TRIGGER after_image_delete
                AFTER DELETE ON images
                FOR EACH ROW
                EXECUTE FUNCTION log_deleted_image();
            ');
        } else {
            throw new \Exception("Unsupported DB driver: $driver");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS after_image_delete;');
        } elseif ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS after_image_delete ON images;');
            DB::unprepared('DROP FUNCTION IF EXISTS log_deleted_image();');
        }
        Schema::dropIfExists('deleted_images');
        Schema::dropIfExists('images');
    }
};
