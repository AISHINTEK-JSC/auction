<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Shrinks uploaded photos before they are stored. A phone photo is several
 * megabytes and thousands of pixels wide; the site never shows it larger than
 * a screen, and mostly as a small card.
 *
 * Re-encoding also drops the metadata a camera embeds (GPS position included).
 */
class ImageOptimizer
{
    /** Longest side of the picture shown on a listing page and when zoomed. */
    public const LARGE = 1600;

    /** Longest side of the picture shown in cards and thumbnail strips. */
    public const THUMB = 480;

    /** Side of the square a profile picture is cropped to. */
    public const AVATAR = 256;

    /** Beyond this the picture is refused rather than decoded: it would not fit in memory. */
    public const MAX_SIDE = 8000;

    private const JPEG_QUALITY = 82;

    private const WEBP_QUALITY = 80;

    /**
     * Validation rules for an uploaded picture that will go through here.
     *
     * @return list<string>
     */
    public static function rules(int $maxKilobytes): array
    {
        return ['image', 'max:'.$maxKilobytes, 'dimensions:max_width='.self::MAX_SIDE.',max_height='.self::MAX_SIDE];
    }

    /**
     * The two versions of a listing photo.
     *
     * The large one is a JPEG because link previews (Facebook, Zalo…) read it
     * and not all of them accept WebP; the thumbnail only ever goes to browsers.
     *
     * @return array{large: string, thumb: string} encoded image data
     *
     * @throws RuntimeException when the file cannot be read as an image
     */
    public static function listing(string $file): array
    {
        $image = self::open($file);

        return [
            'large' => self::jpeg(self::fit($image, self::LARGE)),
            'thumb' => self::webp(self::fit($image, self::THUMB)),
        ];
    }

    /**
     * A profile picture: the centre of the photo as a small square.
     *
     * @throws RuntimeException when the file cannot be read as an image
     */
    public static function avatar(string $file): string
    {
        $image = self::open($file);
        $side = min(imagesx($image), imagesy($image));
        $size = min($side, self::AVATAR);

        $square = self::canvas($size, $size);
        imagecopyresampled(
            $square, $image, 0, 0,
            intdiv(imagesx($image) - $side, 2), intdiv(imagesy($image) - $side, 2),
            $size, $size, $side, $side,
        );

        return self::webp($square);
    }

    /**
     * Decode the file and turn it the way the camera was held.
     */
    private static function open(string $file): GdImage
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('The GD extension is not available.');
        }

        $size = @getimagesize($file);

        if ($size === false || $size[0] > self::MAX_SIDE || $size[1] > self::MAX_SIDE) {
            throw new RuntimeException('Not an image, or too large to process.');
        }

        $image = @imagecreatefromstring((string) file_get_contents($file));

        if ($image === false) {
            throw new RuntimeException('The image could not be decoded.');
        }

        // Palette images (GIF, some PNG) cannot be resampled cleanly.
        imagepalettetotruecolor($image);

        return self::upright($image, $size[2] === IMAGETYPE_JPEG ? $file : null);
    }

    /**
     * Phones store photos sideways and note the rotation in the metadata. That
     * note is lost when the picture is re-encoded, so the rotation is applied.
     */
    private static function upright(GdImage $image, ?string $jpeg): GdImage
    {
        if ($jpeg === null || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) ((@exif_read_data($jpeg) ?: [])['Orientation'] ?? 1);

        // Mirrored variants (2, 4, 5, 7) first, then the rotation they share with 1, 3, 6, 8.
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $degrees = match ($orientation) {
            3, 4 => 180,
            5, 8 => 90,
            6, 7 => 270,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $degrees, 0);

        return $rotated === false ? $image : $rotated;
    }

    /**
     * A copy no longer than $max on its longest side. Never enlarges.
     */
    private static function fit(GdImage $image, int $max): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, $max / max($width, $height));
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $copy = self::canvas($newWidth, $newHeight);
        imagecopyresampled($copy, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $copy;
    }

    /**
     * An empty, fully transparent picture.
     *
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private static function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            throw new RuntimeException('Out of memory while resizing the image.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 255, 255, 255, 127));

        return $canvas;
    }

    private static function jpeg(GdImage $image): string
    {
        // JPEG has no transparency: see-through areas become white, not black.
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));

        if ($flat === false) {
            throw new RuntimeException('Out of memory while encoding the image.');
        }

        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        // Progressive: the picture appears at once and sharpens as it loads.
        imageinterlace($flat, true);

        return self::encode(fn () => imagejpeg($flat, null, self::JPEG_QUALITY));
    }

    private static function webp(GdImage $image): string
    {
        return self::encode(fn () => imagewebp($image, null, self::WEBP_QUALITY));
    }

    /**
     * @param  callable(): bool  $write  prints the encoded image
     */
    private static function encode(callable $write): string
    {
        ob_start();

        try {
            $written = $write();
        } finally {
            $data = (string) ob_get_clean();
        }

        if (! $written || $data === '') {
            throw new RuntimeException('The image could not be encoded.');
        }

        return $data;
    }
}
