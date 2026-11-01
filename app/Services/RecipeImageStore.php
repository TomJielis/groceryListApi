<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Stores recipe photos on the private disk. The photo is saved again as a JPEG of at most MAX_SIZE pixels: that
// keeps it light for the server, and drops the metadata phone photos carry (like where it was taken). A small copy of
// THUMB_SIZE pixels is saved next to it for the overview, where the tiles are only a few centimetres wide.
class RecipeImageStore
{
    private const MAX_SIZE = 1600;

    private const THUMB_SIZE = 480;

    private const QUALITY = 82;

    /**
     * The photo in a data URL like the app sends ("data:image/jpeg;base64,..."); null when it isn't one, or when its
     * contents aren't a picture GD can read
     */
    public function decodeDataUrl(string $value): ?string
    {
        if (!preg_match('#^data:image/(jpeg|jpg|png|webp|heic);base64,#i', $value, $match)) {
            return null;
        }
        $contents = base64_decode(substr($value, strlen($match[0])), true);
        if ($contents === false || $contents === '') {
            return null;
        }
        // Without GD it can't be checked (nor re-encoded): the server then keeps what was sent
        if (function_exists('imagecreatefromstring') && @imagecreatefromstring($contents) === false) {
            return null;
        }

        return $contents;
    }

    /**
     * Saves the photo and its small copy (Recipe::thumbPath) and returns the photo's path on the disk
     */
    public function store(string $contents): string
    {
        $path = 'recipes/' . Str::uuid() . '.jpg';
        $disk = Storage::disk(Recipe::IMAGE_DISK);
        $disk->put($path, $this->reencode($contents, self::MAX_SIZE) ?? $contents);
        $disk->put(Recipe::thumbPath($path), $this->reencode($contents, self::THUMB_SIZE) ?? $contents);

        return $path;
    }

    // Null when GD isn't installed or can't read the image: the original is kept then
    private function reencode(string $contents, int $maxSize): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }

        $image = $this->upright($image, $contents);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxSize / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            if ($resized !== false) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        ob_start();
        imagejpeg($image, null, self::QUALITY);
        imagedestroy($image);

        return ob_get_clean() ?: null;
    }

    // Phones save photos sideways with a note on how to turn them; the new JPEG has no such note, so turn it now
    private function upright(\GdImage $image, string $contents): \GdImage
    {
        if (!function_exists('exif_read_data') || !str_starts_with($contents, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($contents));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }
}
