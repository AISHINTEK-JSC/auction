<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    private const MAX_PER_MEMBER = 20;

    public function index(Request $request): Response
    {
        return Inertia::render('saved-searches/Index', [
            'searches' => array_values($request->user()->savedSearches()
                ->with(['category', 'city'])
                ->latest('id')
                ->get()
                ->map(fn (SavedSearch $search) => [
                    'id' => $search->id,
                    'label' => $search->label(),
                    'url' => $search->url(),
                    'created_at' => $search->created_at?->toIso8601String(),
                ])
                ->all()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'type' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $criteria = [
            'q' => filled($data['q'] ?? null) ? trim((string) $data['q']) : null,
            'category_id' => $data['category_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'type' => $data['type'] ?? null,
        ];

        // A search for "everything" would notify on every single listing.
        if (array_filter($criteria, fn ($value) => $value !== null) === []) {
            throw ValidationException::withMessages(['q' => 'Hãy chọn ít nhất một tiêu chí (từ khoá, danh mục, khu vực hoặc hình thức) trước khi lưu.']);
        }

        $searches = $request->user()->savedSearches();

        if (! (clone $searches)->where($criteria)->exists()) {
            if ((clone $searches)->count() >= self::MAX_PER_MEMBER) {
                throw ValidationException::withMessages(['q' => 'Bạn chỉ lưu được tối đa '.self::MAX_PER_MEMBER.' tìm kiếm. Hãy xoá bớt trước.']);
            }

            $searches->create($criteria);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã lưu. Bạn sẽ được báo khi có tin mới khớp tìm kiếm này.']);

        return back();
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 403);

        $savedSearch->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã xoá tìm kiếm đã lưu.']);

        return back();
    }
}
