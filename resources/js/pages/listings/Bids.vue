<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { formatDateTime, formatVnd } from '@/lib/format';
import { show } from '@/routes/listings';
import { destroy } from '@/routes/listings/bids';
import { BidType } from '@/types';
import type { Bid } from '@/types';

const props = defineProps<{
    listing: { id: number; title: string };
    bids: Bid[];
}>();

function cancel(bid: Bid) {
    if (window.confirm(`Huỷ lượt đặt giá ${formatVnd(bid.price)} của bạn?`)) {
        router.delete(destroy(props.listing.id).url, {
            data: { bid_price: bid.price },
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="`Lịch sử giá: ${listing.title}`" />

    <div class="mx-auto max-w-3xl space-y-4">
        <Link
            :href="show(listing.id)"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            {{ listing.title }}
        </Link>

        <h1 class="text-2xl font-semibold">Lịch sử đặt giá</h1>

        <div class="overflow-x-auto rounded-lg border bg-background">
            <table class="w-full text-sm">
                <thead class="border-b text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-medium">#</th>
                        <th scope="col" class="px-4 py-2 font-medium">
                            Thời gian
                        </th>
                        <th scope="col" class="px-4 py-2 font-medium">
                            Người đặt
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-right font-medium"
                        >
                            Giá đặt
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-2 text-right font-medium"
                        >
                            Giá sau lượt này
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(bid, index) in bids" :key="bid.id">
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ bids.length - index }}
                        </td>
                        <td class="px-4 py-2">
                            {{ formatDateTime(bid.time) }}
                        </td>
                        <td class="px-4 py-2">
                            {{ bid.bidder }}
                            <span
                                v-if="bid.mine"
                                class="text-xs text-muted-foreground"
                                >(bạn)</span
                            >
                            <span
                                v-if="bid.type > BidType.Market"
                                class="text-xs text-muted-foreground"
                                >(tự động)</span
                            >
                            <button
                                v-if="bid.can_cancel"
                                type="button"
                                class="ml-2 text-xs text-muted-foreground underline underline-offset-4 hover:text-destructive"
                                @click="cancel(bid)"
                            >
                                Huỷ
                            </button>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ formatVnd(bid.price) }}
                        </td>
                        <td
                            class="px-4 py-2 text-right font-medium tabular-nums"
                        >
                            {{ formatVnd(bid.current_price) }}
                        </td>
                    </tr>
                    <tr v-if="!bids.length">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            Chưa có lượt đặt giá nào.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
