<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ListingGrid from '@/components/marketplace/ListingGrid.vue';
import Pagination from '@/components/marketplace/Pagination.vue';
import type { ListingCard, Paginated } from '@/types';

defineProps<{
    running: Paginated<ListingCard>;
    upcoming: ListingCard[];
}>();
</script>

<template>
    <Head title="Khám phá" />

    <section class="space-y-4">
        <div class="flex items-baseline justify-between">
            <h1 class="text-2xl font-semibold">Đang mở bán và đấu giá</h1>
            <p class="text-sm text-muted-foreground">
                {{ running.total }} tin đang mở
            </p>
        </div>
        <ListingGrid
            :listings="running.data"
            empty="Hiện chưa có tin nào đang mở."
        />
        <Pagination :page="running" />
    </section>

    <section v-if="upcoming.length" class="mt-10 space-y-4">
        <h2 class="text-xl font-semibold">Sắp diễn ra</h2>
        <ListingGrid :listings="upcoming" />
    </section>
</template>
