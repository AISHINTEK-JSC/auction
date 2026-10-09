<?php

namespace App\Http\Controllers;

use App\Http\Requests\Comments\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Listing $listing): RedirectResponse
    {
        abort_if($listing->auction_id === null, 404);

        $listing->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $request->validated('parent_id'),
            'body' => $request->validated('body'),
        ]);

        return back();
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back();
    }

    /**
     * Toggle the signed-in user's like on a comment.
     */
    public function like(Request $request, Comment $comment): RedirectResponse
    {
        $comment->likers()->toggle($request->user()->id);

        return back();
    }
}
