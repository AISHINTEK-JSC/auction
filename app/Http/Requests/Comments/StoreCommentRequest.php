<?php

namespace App\Http\Requests\Comments;

use App\Models\Listing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $listing = $this->route('listing');

        return [
            'body' => ['required', 'string', 'max:2000'],
            // Replies are one level deep: the parent must be a top-level comment of this listing.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')
                    ->where('listing_id', $listing instanceof Listing ? $listing->id : null)
                    ->whereNull('parent_id'),
            ],
        ];
    }
}
