<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class CleanupOrphanImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-orphan-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : 'public';
        $folder = 'images';

        // All files in storage/images
        $allFiles = Storage::disk($disk)->files($folder);

        // File still exists in images table
        $activeFiles = DB::table('images')
            ->pluck('url')
            ->map(fn($url) => "$folder/$url")
            ->toArray();

        // File has been logged in deleted_images
        $deletedFiles = DB::table('deleted_images')
            ->pluck('url')
            ->map(fn($url) => "$folder/$url")
            ->toArray();

        // Set of files to keep
        $keepFiles = array_merge($activeFiles, $deletedFiles);

        // Set of files to keep
        $orphanFiles = array_diff($allFiles, $keepFiles);

        foreach ($orphanFiles as $file) {
            Storage::disk($disk)->delete($file);
            $this->info("Deleted: $file");
        }

        $this->info("Deleted " . count($orphanFiles) . " image is no longer linked.");
    }
}
