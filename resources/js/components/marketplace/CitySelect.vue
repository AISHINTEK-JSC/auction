<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { computed } from 'vue';
import SearchableSelect from '@/components/marketplace/SearchableSelect.vue';

/**
 * Province picker.
 */
const model = defineModel<number | null>({ required: true });

defineProps<{
    id?: string;
    /** Shown while nothing is chosen, when a city has to be picked. */
    placeholder?: string;
    /** Adds a "no particular city" choice with this label, e.g. "Toàn quốc". */
    allLabel?: string;
}>();

const page = usePage();
const options = computed(() =>
    (page.props.cities ?? []).map((city) => ({
        value: city.id,
        label: city.name,
    })),
);
</script>

<template>
    <SearchableSelect
        :id="id"
        v-model="model"
        :options="options"
        :icon="MapPin"
        :placeholder="placeholder"
        :all-label="allLabel"
        searchable
        search-placeholder="Tìm tỉnh, thành phố…"
        empty-text="Không tìm thấy tỉnh, thành phố nào."
    />
</template>
