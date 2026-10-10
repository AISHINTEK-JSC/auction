<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The photos of a listing: storing uploads (shrunk, with a thumbnail), and
 * applying the seller's changes to an existing listing.
 */
class ListingImages
{
    /**
     * Drop some images, reorder the rest, append new uploads.
     *
     * Run inside the caller's transaction. Files are not deleted here: delete
     * "removed" once the transaction has committed, and "stored" if it failed.
     *
     * @param  list<int|string>  $removeIds  ids of stored images to drop
     * @param  list<int|string>  $order  ids of the images that stay, in the wanted order; the first is the cover
     * @param  list<UploadedFile>  $uploads  appended after the ones that stay
     * @return array{stored: list<string>, removed: list<string>}
     */
    public function sync(Listing $listing, array $removeIds, array $order, array $uploads): array
    {
        $removed = $listing->images()->whereIn('id', $removeIds)->get();
        $listing->images()->whereKey($removed->modelKeys())->delete();

        // Ids that are not this listing's are simply never matched.
        $order = array_map('intval', $order);
        $kept = $listing->images()->get()->sortBy(fn ($image) => [
            ($index = array_search($image->id, $order, true)) === false ? PHP_INT_MAX : $index,
            $image->position,
        ]);

        $position = 0;

        foreach ($kept as $image) {
            $image->update(['position' => ++$position]);
        }

        $stored = [];

        foreach ($uploads as $upload) {
            array_push($stored, ...self::add($listing, $upload, ++$position)->files());
        }

        return [
            'stored' => $stored,
            'removed' => self::files($removed),
        ];
    }

    /**
     * Store an upload as an image of the listing: a large version and a
     * thumbnail, both re-encoded. A file that cannot be processed (no GD, a
     * format it does not read) is kept as uploaded, without a thumbnail.
     *
     * @throws RuntimeException when the disk refuses the file
     */
    public static function add(Listing $listing, UploadedFile $upload, int $position): ListingImage
    {
        $directory = "listings/{$listing->id}";

        try {
            $versions = ImageOptimizer::listing($upload->getRealPath());
        } catch (RuntimeException $e) {
            report($e);

            $path = $upload->store($directory, 'public');

            if ($path === false) {
                throw new RuntimeException('Không lưu được hình ảnh.');
            }

            return $listing->images()->create(['path' => $path, 'position' => $position]);
        }

        [$path, $thumbPath] = self::write($directory, $versions);

        return $listing->images()->create(['path' => $path, 'thumb_path' => $thumbPath, 'position' => $position]);
    }

    /**
     * Put both versions on the public disk under one random name.
     *
     * @param  array{large: string, thumb: string}  $versions
     * @return array{string, string} the large path and the thumbnail path
     *
     * @throws RuntimeException when the disk refuses a file
     */
    public static function write(string $directory, array $versions): array
    {
        $name = Str::random(40);
        $path = "{$directory}/{$name}.jpg";
        $thumbPath = "{$directory}/{$name}_thumb.webp";
        $disk = Storage::disk('public');

        if (! $disk->put($path, $versions['large']) || ! $disk->put($thumbPath, $versions['thumb'])) {
            $disk->delete([$path, $thumbPath]);

            throw new RuntimeException('Không lưu được hình ảnh.');
        }

        return [$path, $thumbPath];
    }

    /**
     * Copy an image, files included, to another listing.
     *
     * @return ListingImage|null null when the source file is gone
     */
    public static function copy(ListingImage $image, Listing $listing, int $position): ?ListingImage
    {
        $disk = Storage::disk('public');
        $path = "listings/{$listing->id}/".basename($image->path);

        if (! $disk->copy($image->path, $path)) {
            return null;
        }

        $thumbPath = $image->thumb_path === null ? null : "listings/{$listing->id}/".basename($image->thumb_path);

        // A lost thumbnail is no reason to lose the image: it shows its large version.
        if ($thumbPath !== null && ! $disk->copy($image->thumb_path, $thumbPath)) {
            $thumbPath = null;
        }

        return $listing->images()->create(['path' => $path, 'thumb_path' => $thumbPath, 'position' => $position]);
    }

    /**
     * Every file the given images own on the public disk.
     *
     * @param  iterable<ListingImage>  $images
     * @return list<string>
     */
    public static function files(iterable $images): array
    {
        $files = [];

        foreach ($images as $image) {
            array_push($files, ...$image->files());
        }

        return $files;
    }
}
