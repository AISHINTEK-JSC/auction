<script setup lang="ts" generic="T extends string | number">
import { Check, ChevronsUpDown, Search } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';

export type SelectOption<V> = {
    value: V;
    label: string;
    /** 1 for an option nested under the one before it (a sub-category). */
    depth?: number;
    /** Text for the closed select when it differs from the label, e.g. "Parent › Child". */
    selectedLabel?: string;
    /** Extra text the search should match, e.g. the parent's name. */
    keywords?: string;
};

/**
 * The marketplace's select: a button that opens a list, with a search box
 * when the list is long. Typing without accents works ("da nang" finds
 * "Đà Nẵng").
 */
const model = defineModel<T | null>({ required: true });

const props = withDefaults(
    defineProps<{
        options: SelectOption<T>[];
        id?: string;
        icon?: Component;
        /** Shown while nothing is chosen, when something has to be picked. */
        placeholder?: string;
        /** Adds a "no particular choice" option with this label, e.g. "Toàn quốc". */
        allLabel?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        /** Defaults to on for lists of more than 7 options. */
        searchable?: boolean;
    }>(),
    {
        id: undefined,
        icon: undefined,
        placeholder: 'Chọn',
        allLabel: undefined,
        searchPlaceholder: 'Tìm…',
        emptyText: 'Không tìm thấy.',
        searchable: undefined,
    },
);

const ALL = '__all__';

const open = ref(false);
const search = ref('');

const hasSearch = computed(() => props.searchable ?? props.options.length > 7);

// The combobox works on strings; the model keeps the option's own value type.
const value = computed({
    get: () => (model.value === null ? ALL : String(model.value)),
    set: (next: string) => {
        model.value =
            next === ALL
                ? null
                : (props.options.find((option) => String(option.value) === next)
                      ?.value ?? null);
    },
});

const selected = computed(
    () => props.options.find((option) => option.value === model.value) ?? null,
);

/** Lower case, accents removed, đ → d. */
function fold(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .trim();
}

const matches = computed(() => {
    const term = fold(search.value);

    return term
        ? props.options.filter((option) =>
              fold(`${option.label} ${option.keywords ?? ''}`).includes(term),
          )
        : props.options;
});

watch(open, (isOpen) => {
    if (!isOpen) {
        search.value = '';
    }
});

const itemClass =
    'relative flex cursor-pointer items-center rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground';
</script>

<template>
    <!-- Filtering is done here (accent-insensitive), not by the combobox. -->
    <ComboboxRoot
        v-model="value"
        v-model:open="open"
        ignore-filter
        :reset-search-term-on-blur="false"
    >
        <ComboboxAnchor as-child>
            <ComboboxTrigger
                :id="id"
                class="flex h-9 w-full items-center gap-2 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 data-[state=open]:border-ring"
            >
                <component
                    :is="icon"
                    v-if="icon"
                    class="size-4 shrink-0 text-muted-foreground"
                />
                <span
                    class="flex-1 truncate text-left"
                    :class="{
                        'text-muted-foreground': !selected && !allLabel,
                    }"
                >
                    {{
                        selected
                            ? (selected.selectedLabel ?? selected.label)
                            : (allLabel ?? placeholder)
                    }}
                </span>
                <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                align="start"
                :side-offset="4"
                class="z-50 w-(--reka-combobox-trigger-width) min-w-56 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <div
                    v-if="hasSearch"
                    class="flex items-center gap-2 border-b px-3"
                >
                    <Search class="size-4 shrink-0 text-muted-foreground" />
                    <!-- display-value keeps the search box empty after a pick;
                         by default it would be filled with the chosen value. -->
                    <ComboboxInput
                        v-model="search"
                        :display-value="() => ''"
                        auto-focus
                        :placeholder="searchPlaceholder"
                        :aria-label="searchPlaceholder"
                        class="h-9 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    />
                </div>

                <ComboboxViewport class="max-h-64 overflow-y-auto p-1">
                    <ComboboxEmpty
                        class="px-3 py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ emptyText }}
                    </ComboboxEmpty>

                    <ComboboxItem
                        v-if="allLabel && !search"
                        :value="ALL"
                        :class="itemClass"
                    >
                        {{ allLabel }}
                        <ComboboxItemIndicator class="absolute right-2">
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>

                    <ComboboxItem
                        v-for="option in matches"
                        :key="option.value"
                        :value="String(option.value)"
                        :class="[
                            itemClass,
                            // Nested options are indented, unless a search has flattened the list.
                            option.depth && !search ? 'pl-7' : '',
                            !option.depth && options.some((o) => o.depth)
                                ? 'font-medium'
                                : '',
                        ]"
                    >
                        {{
                            search && option.selectedLabel
                                ? option.selectedLabel
                                : option.label
                        }}
                        <ComboboxItemIndicator class="absolute right-2">
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
