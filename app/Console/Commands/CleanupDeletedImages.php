<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CleanupDeletedImages extends Command
{
    protected $signature = 'images:cleanup-deleted';
    protected $description = 'Delete logged image file from SQL trigger';

    public function handle()
    {
        $disk = isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : 'public';

        $entries = DB::table('deleted_images')->get();

        foreach ($entries as $entry) {
            $path = 'images/' . $entry->url;

            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
                $this->info("Deleted file: $path");
            } else {
                $this->info("File not found: $path");
            }

            DB::table('deleted_images')->where('id', $entry->id)->delete();
        }

        $this->info("Cleanup done.");
    }
}