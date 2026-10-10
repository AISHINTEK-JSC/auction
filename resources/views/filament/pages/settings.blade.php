{{-- Filament ships its own compiled stylesheet; this page carries the few styles it needs. --}}
<x-filament-panels::page>
    <style>
        .mk-settings { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); }
        .mk-settings__card { border: 1px solid rgb(128 128 128 / 0.25); border-radius: 0.75rem; overflow: hidden; }
        .mk-settings__title { padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 600; background: rgb(128 128 128 / 0.1); }
        .mk-settings dl { margin: 0; }
        .mk-settings__row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.625rem 1rem; font-size: 0.875rem; }
        .mk-settings__row + .mk-settings__row { border-top: 1px solid rgb(128 128 128 / 0.15); }
        .mk-settings dt { opacity: 0.75; }
        .mk-settings dt { flex-shrink: 0; }
        /* Addresses and server names can be long: let them wrap instead of pushing the card wider. */
        .mk-settings dd { margin: 0; font-weight: 600; text-align: right; overflow-wrap: anywhere; }
    </style>

    <div class="mk-settings">
        @foreach ($groups as $title => $rows)
            <section class="mk-settings__card">
                <h2 class="mk-settings__title">{{ $title }}</h2>
                <dl>
                    @foreach ($rows as $label => $value)
                        <div class="mk-settings__row">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
