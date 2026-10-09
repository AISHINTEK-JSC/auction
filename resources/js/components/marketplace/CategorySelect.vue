<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import { computed } from 'vue';
import SearchableSelect from '@/components/marketplace/SearchableSelect.vue';

/**
 * Category picker: top-level categories with their sub-categories indented
 * underneath. Searching also matches the parent's name.
 */
const model = defineModel<number | null>({ required: true });

defineProps<{
    id?: string;
    placeholder?: string;
}>();

const page = usePage();
const options = computed(() =>
    (page.props.categories ?? []).flatMap((category) => [
        { value: category.id, label: category.name },
        ...category.children.map((child) => ({
            value: child.id,
            label: child.name,
            depth: 1,
            selectedLabel: `${category.name} › ${child.name}`,
            keywords: category.name,
        })),
    ]),
);
</script>

<template>
    <SearchableSelect
        :id="id"
        v-model="model"
        :options="options"
        :icon="LayoutGrid"
        :placeholder="placeholder"
        searchable
        search-placeholder="Tìm danh mục…"
        empty-text="Không tìm thấy danh mục nào."
    />
</template>
