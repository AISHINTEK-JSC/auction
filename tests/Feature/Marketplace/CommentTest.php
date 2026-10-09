<?php

namespace Tests\Feature\Marketplace;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_comment_and_reply_one_level_deep(): void
    {
        $listing = Listing::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('listings.comments.store', $listing), ['body' => 'Còn hàng không?'])
            ->assertSessionHasNoErrors();

        $comment = Comment::query()->sole();

        $this->actingAs($listing->user)
            ->post(route('listings.comments.store', $listing), ['body' => 'Còn nhé', 'parent_id' => $comment->id])
            ->assertSessionHasNoErrors();

        $reply = Comment::query()->whereNotNull('parent_id')->sole();

        // No replying to a reply, and no parent from another listing.
        $this->actingAs($user)
            ->post(route('listings.comments.store', $listing), ['body' => 'x', 'parent_id' => $reply->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($user)
            ->post(route('listings.comments.store', Listing::factory()->create()), ['body' => 'x', 'parent_id' => $comment->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_liking_a_comment_toggles(): void
    {
        $comment = Comment::query()->create([
            'listing_id' => Listing::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'Hay',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('comments.like', $comment));
        $this->assertSame(1, $comment->likers()->count());

        $this->actingAs($user)->post(route('comments.like', $comment));
        $this->assertSame(0, $comment->likers()->count());
    }

    public function test_only_the_author_or_an_admin_can_delete_a_comment(): void
    {
        $author = User::factory()->create();
        $comment = Comment::query()->create([
            'listing_id' => Listing::factory()->create()->id,
            'user_id' => $author->id,
            'body' => 'Hay',
        ]);

        $this->actingAs(User::factory()->create())
            ->delete(route('comments.destroy', $comment))
            ->assertForbidden();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('comments.destroy', $comment))->assertRedirect();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_guests_cannot_comment(): void
    {
        $this->post(route('listings.comments.store', Listing::factory()->create()), ['body' => 'x'])
            ->assertRedirect(route('login'));
    }
}
