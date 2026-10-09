{{--
    Entries of a giveaway, shown in a modal of the admin panel.

    Filament ships its own compiled stylesheet, so arbitrary Tailwind utilities
    are not available here: this view carries its own small set of styles.
--}}
@php
    $winners = $entries->where('is_winner', true)->count();
@endphp

<style>
    .gw-entries { font-size: 0.875rem; line-height: 1.25rem; }
    .gw-entries__summary { display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem; margin-bottom: 0.75rem; opacity: 0.75; }
    .gw-entries__summary strong { font-weight: 600; opacity: 1; }
    .gw-entries__empty { padding: 1.5rem 0; text-align: center; opacity: 0.7; }
    .gw-entries__scroll { max-height: 60vh; overflow: auto; border: 1px solid rgb(128 128 128 / 0.25); border-radius: 0.5rem; }
    .gw-entries table { width: 100%; border-collapse: collapse; text-align: left; }
    .gw-entries th,
    .gw-entries td { padding: 0.625rem 0.875rem; white-space: nowrap; }
    .gw-entries th { position: sticky; top: 0; z-index: 1; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase; background: rgb(128 128 128 / 0.12); backdrop-filter: blur(6px); }
    .gw-entries tbody tr + tr td { border-top: 1px solid rgb(128 128 128 / 0.18); }
    .gw-entries tbody tr:hover td { background: rgb(128 128 128 / 0.06); }
    .gw-entries__code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 600; letter-spacing: 0.08em; }
    .gw-entries__email { opacity: 0.75; }
    .gw-entries__muted { opacity: 0.5; }
    .gw-entries tr.is-winner td { background: rgb(34 197 94 / 0.08); }
</style>

<div class="gw-entries">
    @if ($entries->isEmpty())
        <p class="gw-entries__empty">Chưa có ai đăng ký tham gia.</p>
    @else
        <p class="gw-entries__summary">
            <span><strong>{{ $entries->count() }}</strong> người tham gia</span>
            <span>
                @if ($winners > 0)
                    <strong>{{ $winners }}</strong> người trúng thưởng
                @else
                    Chưa quay thưởng
                @endif
            </span>
        </p>

        <div class="gw-entries__scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Mã số</th>
                        <th scope="col">Thành viên</th>
                        <th scope="col">Email</th>
                        <th scope="col">Đăng ký lúc</th>
                        <th scope="col">Kết quả</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr @class(['is-winner' => $entry->is_winner])>
                            <td class="gw-entries__code">{{ $entry->user->member_code }}</td>
                            <td>{{ $entry->user->name }}</td>
                            <td class="gw-entries__email">{{ $entry->user->email }}</td>
                            <td>{{ $entry->created_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($entry->is_winner)
                                    <x-filament::badge color="success">Trúng thưởng</x-filament::badge>
                                @else
                                    <span class="gw-entries__muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
