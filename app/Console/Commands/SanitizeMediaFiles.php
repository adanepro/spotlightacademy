<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class SanitizeMediaFiles extends Command
{
    protected $signature = 'media:sanitize';
    protected $description = 'Sanitize filenames of all media files to be URL safe';

    public function handle()
    {
        $this->info("Starting to sanitize media files...");

        $medias = Media::all();

        foreach ($medias as $media) {
            $oldFilePath = $media->getPath(); // full path
            $oldFileName = $media->file_name;

            // Sanitize the filename
            $newFileName = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $oldFileName);

            // Skip if already safe
            if ($newFileName === $oldFileName) {
                continue;
            }

            $newFilePath = dirname($oldFilePath) . '/' . $newFileName;

            // Rename the physical file
            if (file_exists($oldFilePath)) {
                rename($oldFilePath, $newFilePath);
            }

            // Update media record
            $media->file_name = $newFileName;
            $media->save();

            $this->info("Renamed: {$oldFileName} → {$newFileName}");
        }

        $this->info("All media files sanitized successfully.");
    }
}
