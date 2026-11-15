<?php

namespace App\Support\Demo;

use Illuminate\Support\Facades\Storage;

/**
 * The demo food pictures (docs/demo-food, see its real/README.md).
 *
 * Each item is shown with the first of these that exists:
 *   1. a real photo of the item      docs/demo-food/real/kuih-lapis.jpg
 *   2. a drawing of the item         docs/demo-food/kuih-lapis.svg
 *   3. a real photo of its kind      docs/demo-food/real/kuih.jpg
 *   4. the drawing of its kind       docs/demo-food/kuih.svg
 */
class DemoPhotos
{
    /** Real photo types that are accepted, in order of preference. */
    public const TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    /** Where listing photos of the demo live on the public disk. */
    private const TARGET = 'listings/demo';

    /**
     * Copy the pictures to where listing photos live.
     *
     * $clean starts from an empty folder, so a photo that was taken out of
     * docs/demo-food/real is not used again (the seeder does this). Without
     * it nothing is removed, because older listings may still show a file
     * (demo:refresh does this). Sellers' own uploads are in listings/ itself
     * and are never touched.
     */
    public static function copy(bool $clean = false): void
    {
        if ($clean) {
            Storage::disk('public')->deleteDirectory(self::TARGET);
        }

        $folder = self::folder();

        $files = [
            ...glob($folder.'/*.svg') ?: [],
            ...glob($folder.'/real/*.{'.implode(',', self::TYPES).'}', GLOB_BRACE) ?: [],
        ];

        foreach ($files as $file) {
            Storage::disk('public')->put(self::TARGET.'/'.basename($file), file_get_contents($file));
        }
    }

    /**
     * The picture for an item.  path('curry-puffs') →
     * "listings/demo/curry-puffs.jpg" or "listings/demo/curry-puffs.svg";
     * path('kuih-lapis', 'kuih') → "listings/demo/kuih.svg" while kuih lapis
     * has no picture of its own.
     */
    public static function path(string $name, ?string $fallback = null): string
    {
        $folder = self::folder();

        foreach (array_filter([$name, $fallback]) as $candidate) {
            foreach (self::TYPES as $type) {
                if (is_file("{$folder}/real/{$candidate}.{$type}")) {
                    return self::TARGET."/{$candidate}.{$type}";
                }
            }

            if (is_file("{$folder}/{$candidate}.svg")) {
                return self::TARGET."/{$candidate}.svg";
            }
        }

        return self::TARGET."/{$name}.svg";
    }

    private static function folder(): string
    {
        return config('foodloop.demo_photo_dir');
    }
}
