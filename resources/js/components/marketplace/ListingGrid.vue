<script setup lang="ts">
import AuctionCard from '@/components/marketplace/AuctionCard.vue';
import type { ListingCard } from '@/types';

defineProps<{
    listings: ListingCard[];
    empty?: string;
}>();
</script>

<template>
    <div
        v-if="listings.length"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
    >
        <AuctionCard
            v-for="listing in listings"
            :key="listing.id"
            :listing="listing"
        />
    </div>
    <p
        v-else
        class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
    >
        {{ empty ?? 'Chưa có phiên đấu giá nào.' }}
    </p>
</template>
