{{-- Filament ships its own compiled stylesheet; this page carries the few styles it needs. --}}
<x-filament-panels::page>
    <style>
        .mt-layout { display: flex; flex-wrap: wrap; gap: 0.5rem 2rem; padding: 0.875rem 1rem; border: 1px solid rgb(128 128 128 / 0.25); border-radius: 0.75rem; font-size: 0.875rem; }
        .mt-layout dt { display: inline; opacity: 0.7; }
        .mt-layout dd { display: inline; margin: 0 0 0 0.375rem; font-weight: 600; }
        .mt-swatch { display: inline-block; width: 0.875rem; height: 0.875rem; margin-right: 0.375rem; border-radius: 0.25rem; vertical-align: -0.125rem; border: 1px solid rgb(128 128 128 / 0.4); }
        .mt-group { border: 1px solid rgb(128 128 128 / 0.25); border-radius: 0.75rem; overflow: hidden; }
        .mt-group + .mt-group { margin-top: 1.5rem; }
        .mt-group__title { padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 600; background: rgb(128 128 128 / 0.1); }
        .mt-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1.5rem; padding: 0.75rem 1rem; font-size: 0.875rem; }
        .mt-row + .mt-row { border-top: 1px solid rgb(128 128 128 / 0.15); }
        .mt-row__text { min-width: 0; flex: 1 1 18rem; }
        .mt-row__label { font-weight: 600; }
        .mt-row__subject { margin-top: 0.125rem; opacity: 0.7; overflow-wrap: anywhere; }
        .mt-row__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
        .mt-tag { display: inline-block; margin-left: 0.5rem; padding: 0.0625rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 500; vertical-align: 0.0625rem; background: rgb(128 128 128 / 0.18); }
        .mt-tag--edited { background: rgb(245 158 11 / 0.2); color: rgb(217 119 6); }
    </style>

    <dl class="mt-layout">
        <div><dt>Màu chủ đạo</dt><dd><span class="mt-swatch" style="background: {{ $layout['brand_color'] }}"></span>{{ $layout['brand_color'] }}</dd></div>
        <div><dt>Logo</dt><dd>{{ $layout['show_logo'] ? 'Hiện' : 'Ẩn' }}</dd></div>
        <div><dt>Lời chào</dt><dd>{{ $layout['greeting'] }}</dd></div>
        <div><dt>Chữ ký</dt><dd>{{ str_replace("\n", ' / ', $layout['signature']) }}</dd></div>
    </dl>

    <div>
        @foreach ($groups as $group => $types)
            <section class="mt-group">
                <h2 class="mt-group__title">{{ $group }}</h2>
                @foreach ($types as $type => $row)
                    <div class="mt-row">
                        <div class="mt-row__text">
                            <div class="mt-row__label">
                                {{ $row['label'] }}
                                @if ($row['customized'])
                                    <span class="mt-tag mt-tag--edited">Đã sửa</span>
                                @endif
                                @if ($row['mail_only'])
                                    <span class="mt-tag">Chỉ email</span>
                                @endif
                            </div>
                            <div class="mt-row__subject">{{ $row['subject'] }}</div>
                        </div>
                        <div class="mt-row__actions">
                            {{ ($this->editContentAction)(['type' => $type]) }}
                            {{ ($this->previewAction)(['type' => $type]) }}
                            {{ ($this->sendTestAction)(['type' => $type]) }}
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
