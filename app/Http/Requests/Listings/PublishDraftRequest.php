<?php

namespace App\Http\Requests\Listings;

use App\Models\Listing;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Publishing a draft is posting a listing: the same rules apply in full. The
 * only differences are that the draft's own images count, and that the seller
 * may drop or reorder them in the same step.
 */
class PublishDraftRequest extends StoreListingRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageDraft', $this->draft());
    }

    public function draft(): Listing
    {
        $listing = $this->route('listing');

        abort_unless($listing instanceof Listing, 404);

        return $listing;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'image_order' => ['nullable', 'array'],
            'image_order.*' => ['integer'],
        ];
    }

    protected function reusedImageCount(): int
    {
        return $this->draft()->images()->whereNotIn('id', $this->input('remove_images', []))->count();
    }
}
