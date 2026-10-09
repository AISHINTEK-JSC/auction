<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Applies the seller's changes to the images of an existing listing: drop
 * some, reorder the rest, append new uploads.
 */
class ListingImages
{
    /**
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
            $stored[] = $path = self::store($listing, $upload);
            $listing->images()->create(['path' => $path, 'position' => ++$position]);
        }

        return [
            'stored' => $stored,
            'removed' => array_values($removed->map(fn ($image) => $image->path)->all()),
        ];
    }

    /**
     * Put an upload on the public disk, in the listing's folder.
     *
     * @throws RuntimeException when the disk refuses the file
     */
    public static function store(Listing $listing, UploadedFile $upload): string
    {
        $path = $upload->store("listings/{$listing->id}", 'public');

        if ($path === false) {
            throw new RuntimeException('Không lưu được hình ảnh.');
        }

        return $path;
    }
}
