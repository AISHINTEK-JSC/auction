<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingImages;
use App\Support\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Uploaded photos are shrunk before they are stored, and come with a
 * thumbnail for the pages that show many of them.
 */
class ImageOptimizationTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
        Storage::fake('public');
        Http::fake();
    }

    /**
     * A JPEG file of the given size, optionally tagged with an EXIF orientation
     * the way a phone tags a photo taken sideways.
     */
    private function jpeg(int $width, int $height, ?int $orientation = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 60, 40));
        // A marker in the top-left corner, to tell which way is up afterwards.
        imagefilledrectangle($image, 0, 0, intdiv($width, 4), intdiv($height, 4), (int) imagecolorallocate($image, 20, 40, 220));

        ob_start();
        imagejpeg($image, null, 90);
        $data = (string) ob_get_clean();

        if ($orientation !== null) {
            // Little-endian TIFF with a single tag: 0x0112 (Orientation), SHORT.
            $tiff = "II*\0".pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, $orientation, 0).pack('V', 0);
            $exif = "Exif\0\0".$tiff;
            $data = substr($data, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($data, 2);
        }

        $file = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($file, $data);

        return $file;
    }

    /**
     * @return array{int, int, int} width, height, image type
     */
    private function measure(string $data): array
    {
        $size = getimagesizefromstring($data);
        $this->assertNotFalse($size);

        return [$size[0], $size[1], $size[2]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'sale_type' => 'fixed',
            'title' => 'Bàn làm việc',
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
            'condition' => 'used',
            'images' => [new UploadedFile($this->jpeg(3000, 2000), 'photo.jpg', 'image/jpeg', null, true)],
            'start_price' => 800000,
            ...$overrides,
        ];
    }

    public function test_a_large_photo_is_shrunk_and_gets_a_thumbnail(): void
    {
        $versions = ImageOptimizer::listing($this->jpeg(3000, 2000));

        $this->assertSame([1600, 1067, IMAGETYPE_JPEG], $this->measure($versions['large']));
        $this->assertSame([480, 320, IMAGETYPE_WEBP], $this->measure($versions['thumb']));
    }

    public function test_a_small_photo_is_never_enlarged(): void
    {
        $versions = ImageOptimizer::listing($this->jpeg(300, 200));

        $this->assertSame([300, 200, IMAGETYPE_JPEG], $this->measure($versions['large']));
        $this->assertSame([300, 200, IMAGETYPE_WEBP], $this->measure($versions['thumb']));
    }

    public function test_a_photo_taken_sideways_is_stored_upright(): void
    {
        // Orientation 6: the stored pixels lie on their side and must be turned a quarter clockwise.
        $versions = ImageOptimizer::listing($this->jpeg(400, 200, orientation: 6));

        [$width, $height] = $this->measure($versions['large']);
        $this->assertSame([200, 400], [$width, $height]);

        // The marker that was top-left in the file is top-right once upright.
        $image = imagecreatefromstring($versions['large']);
        $this->assertNotFalse($image);
        $blue = fn (int $x, int $y): bool => (imagecolorat($image, $x, $y) & 0xFF) > 150 && ((imagecolorat($image, $x, $y) >> 16) & 0xFF) < 100;
        $this->assertTrue($blue(190, 10));
        $this->assertFalse($blue(10, 10));
    }

    public function test_see_through_areas_turn_white_in_the_large_version(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        $file = tempnam(sys_get_temp_dir(), 'img');
        imagepng($image, $file);

        $large = imagecreatefromstring(ImageOptimizer::listing($file)['large']);
        $this->assertNotFalse($large);
        $this->assertGreaterThan(0xF0F0F0, imagecolorat($large, 50, 50) & 0xFFFFFF);
    }

    public function test_a_posted_listing_stores_both_versions_and_shows_the_right_one_in_each_place(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->post(route('listings.store'), $this->payload())->assertSessionHasNoErrors();

        $listing = Listing::query()->sole();
        $image = $listing->images()->sole();

        $this->assertStringEndsWith('.jpg', $image->path);
        $this->assertStringEndsWith('_thumb.webp', (string) $image->thumb_path);
        $this->assertSame([1600, 1067, IMAGETYPE_JPEG], $this->measure((string) Storage::disk('public')->get($image->path)));
        $this->assertSame([480, 320, IMAGETYPE_WEBP], $this->measure((string) Storage::disk('public')->get($image->thumb_path)));
        // Nothing else is kept: the upload itself is gone.
        $this->assertCount(2, Storage::disk('public')->allFiles());

        // Cards get the thumbnail; the listing page gets both; link previews get the large one.
        $this->get(route('listings.index'))->assertInertia(fn (Assert $page) => $page->where('listings.data.0.image', $image->thumb_url));
        $this->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.images.0.url', $image->url)
                ->where('listing.images.0.thumb', $image->thumb_url))
            ->assertSee('<meta property="og:image" content="'.url($image->url).'">', false);
    }

    public function test_removing_an_image_removes_both_files(): void
    {
        $seller = User::factory()->create();
        $this->actingAs($seller)->post(route('listings.store'), $this->payload());
        $listing = Listing::query()->sole();
        $old = $listing->images()->sole();

        $this->actingAs($seller)->put(route('listings.update', $listing), [
            'title' => $listing->title,
            'category_id' => $listing->category_id,
            'city_id' => $listing->city_id,
            'condition' => 'used',
            'remove_images' => [$old->id],
            'images' => [UploadedFile::fake()->image('new.jpg', 200, 100)],
        ])->assertSessionHasNoErrors();

        $new = $listing->images()->sole();
        Storage::disk('public')->assertMissing([$old->path, (string) $old->thumb_path]);
        Storage::disk('public')->assertExists([$new->path, (string) $new->thumb_path]);
        $this->assertCount(2, Storage::disk('public')->allFiles());
    }

    public function test_relisting_copies_both_files_and_deleting_a_draft_removes_them(): void
    {
        $seller = User::factory()->create();
        $this->actingAs($seller)->post(route('listings.store'), $this->payload());
        $old = Listing::query()->sole();
        $old->forceFill(['auction_status' => AuctionStatus::Expired])->save();

        $this->actingAs($seller)->post(route('listings.store'), $this->payload(['relist_from' => $old->id, 'images' => []]))
            ->assertSessionHasNoErrors();

        $copy = Listing::query()->latest('id')->firstOrFail()->images()->sole();
        $this->assertNotSame($old->images()->sole()->path, $copy->path);
        Storage::disk('public')->assertExists([$copy->path, (string) $copy->thumb_path]);

        $draft = Listing::factory()->for($seller)->create(['moderation_status' => ModerationStatus::Draft, 'auction_id' => null]);
        $image = ListingImages::add($draft, UploadedFile::fake()->image('a.jpg', 100, 100), 1);

        $this->actingAs($seller)->delete(route('listings.drafts.destroy', $draft));

        Storage::disk('public')->assertMissing($image->files());
    }

    public function test_a_picture_too_large_to_process_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('listings.store'), $this->payload(['images' => [UploadedFile::fake()->image('wide.png', 8001, 4)]]))
            ->assertSessionHasErrors('images.0');

        $this->assertSame(0, Listing::query()->count());
    }

    public function test_a_file_that_cannot_be_processed_is_kept_as_uploaded(): void
    {
        $listing = Listing::factory()->fixedPrice()->create();

        $image = ListingImages::add($listing, UploadedFile::fake()->create('odd.jpg', 12, 'image/jpeg'), 1);

        $this->assertNull($image->thumb_path);
        Storage::disk('public')->assertExists($image->path);
        // Without a thumbnail, the large version stands in.
        $this->assertSame($image->url, $image->thumb_url);
    }

    public function test_an_avatar_is_cropped_to_a_small_square(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => new UploadedFile($this->jpeg(1200, 800), 'me.jpg', 'image/jpeg', null, true),
        ])->assertSessionHasNoErrors();

        $path = (string) $user->fresh()->avatar_path;
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame([256, 256, IMAGETYPE_WEBP], $this->measure((string) Storage::disk('public')->get($path)));
    }
}
