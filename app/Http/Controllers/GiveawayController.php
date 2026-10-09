<?php

namespace App\Http\Controllers;

use App\Models\Giveaway;
use App\Models\GiveawayEntry;
use App\Models\User;
use App\Services\GiveawayService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GiveawayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('giveaways/Index', [
            'giveaways' => Giveaway::query()
                ->withCount('entries')
                ->orderByRaw('drawn_at is not null')
                ->orderBy('ends_at')
                ->paginate(12)
                ->through(fn (Giveaway $giveaway) => $this->present($giveaway, $user)),
        ]);
    }

    public function show(Request $request, Giveaway $giveaway): Response
    {
        $giveaway->loadCount('entries');

        return Inertia::render('giveaways/Show', [
            'giveaway' => [
                ...$this->present($giveaway, $request->user()),
                'description' => $giveaway->description,
                // Winners are shown by name and member number once the draw is done.
                'winners' => array_values($giveaway->winners()->with('user')->get()
                    ->map(fn (GiveawayEntry $entry) => [
                        'name' => $entry->user->name,
                        'member_code' => $entry->user->member_code,
                    ])
                    ->all()),
            ],
        ]);
    }

    public function join(Request $request, Giveaway $giveaway, GiveawayService $giveaways): RedirectResponse
    {
        try {
            $giveaways->join($giveaway, $request->user());
        } catch (DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã đăng ký tham gia với mã số '.$request->user()->member_code.'.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Giveaway $giveaway, ?User $user): array
    {
        $entry = $user ? $giveaway->entries()->where('user_id', $user->id)->first() : null;

        return [
            'id' => $giveaway->id,
            'title' => $giveaway->title,
            'prize' => $giveaway->prize,
            'image' => $giveaway->image,
            'winner_count' => $giveaway->winner_count,
            'entry_count' => (int) $giveaway->entries_count,
            'ends_at' => $giveaway->ends_at->toIso8601String(),
            'drawn_at' => $giveaway->drawn_at?->toIso8601String(),
            'open' => $giveaway->isOpen(),
            'joined' => $entry !== null,
            'won' => (bool) $entry?->is_winner,
        ];
    }
}
