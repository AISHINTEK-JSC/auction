<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import AuctionCard from '@/components/marketplace/AuctionCard.vue';
import { Button } from '@/components/ui/button';
import { create, destroy } from '@/routes/listings';
import type { ListingCard, Seller } from '@/types';

const props = defineProps<{
    seller: Seller;
    selling: ListingCard[];
    ended: ListingCard[];
    won: ListingCard[];
}>();

type Tab = 'selling' | 'ended' | 'won';

const tabs: { key: Tab; label: string; empty: string }[] = [
    {
        key: 'selling',
        label: 'Đang bán',
        empty: 'Bạn chưa có phiên đấu giá nào đang mở.',
    },
    {
        key: 'ended',
        label: 'Đã kết thúc',
        empty: 'Chưa có phiên nào kết thúc.',
    },
    { key: 'won', label: 'Đã thắng', empty: 'Bạn chưa thắng phiên nào.' },
];

const active = ref<Tab>('selling');
const listings = computed(() => props[active.value]);
const activeTab = computed(() => tabs.find((tab) => tab.key === active.value)!);

function cancel(listing: ListingCard) {
    if (!window.confirm(`Huỷ phiên đấu giá “${listing.title}”?`)) {
        return;
    }

    router.delete(destroy(listing.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Phiên đấu giá của tôi" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Phiên đấu giá của tôi</h1>
                <p class="text-sm text-muted-foreground">
                    {{ seller.listing_count }} tin đã đăng ·
                    {{ seller.sold_count }} phiên thành công
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Đăng tin đấu giá
                </Link>
            </Button>
        </div>

        <div class="flex gap-1 border-b" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="active === tab.key"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium"
                :class="
                    active === tab.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="active = tab.key"
            >
                {{ tab.label }} ({{ props[tab.key].length }})
            </button>
        </div>

        <div
            v-if="listings.length"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
        >
            <div
                v-for="listing in listings"
                :key="listing.id"
                class="flex flex-col gap-2"
            >
                <AuctionCard :listing="listing" class="flex-1" />
                <Button
                    v-if="active === 'selling'"
                    variant="outline"
                    size="sm"
                    @click="cancel(listing)"
                >
                    Huỷ phiên
                </Button>
            </div>
        </div>
        <p
            v-else
            class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
        >
            {{ activeTab.empty }}
        </p>
    </div>
</template>
