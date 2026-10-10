<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Clock,
    Gavel,
    ImageOff,
    MapPin,
    Tag,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import Countdown from '@/components/marketplace/Countdown.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime, formatPrice } from '@/lib/format';
import { edit, show } from '@/routes/listings';
import { edit as editDraft } from '@/routes/listings/drafts';
import { AuctionStatus, AuctionType } from '@/types';
import type { ListingCard } from '@/types';

defineProps<{
    listing: ListingCard;
}>();
</script>

<template>
    <!-- A draft opens in its form; a listing still waiting for approval has no
         auction, hence no detail page. -->
    <Link
        :href="
            listing.moderation === 'draft'
                ? editDraft(listing.id)
                : listing.has_auction
                  ? show(listing.id)
                  : edit(listing.id)
        "
        class="group flex flex-col overflow-hidden rounded-lg border bg-card text-card-foreground transition-shadow hover:shadow-md"
    >
        <div class="relative aspect-square bg-muted">
            <img
                v-if="listing.image"
                :src="listing.image"
                :alt="listing.title"
                loading="lazy"
                decoding="async"
                class="size-full object-cover"
            />
            <div
                v-else
                class="flex size-full items-center justify-center text-muted-foreground"
            >
                <ImageOff class="size-8" />
            </div>

            <Badge
                v-if="listing.sale_type === 'fixed'"
                class="absolute top-2 left-2"
                variant="secondary"
            >
                <Tag />
                Giá cố định
            </Badge>
            <Badge
                v-else
                class="absolute top-2 left-2"
                :variant="
                    listing.auction_type === AuctionType.Down
                        ? 'destructive'
                        : 'default'
                "
            >
                <TrendingDown
                    v-if="listing.auction_type === AuctionType.Down"
                />
                <TrendingUp v-else />
                {{
                    listing.auction_type === AuctionType.Down ? 'Ngược' : 'Xuôi'
                }}
            </Badge>
            <Badge
                v-if="listing.moderation !== 'approved'"
                variant="secondary"
                class="absolute top-2 right-2"
            >
                {{ listing.moderation_label }}
            </Badge>
        </div>

        <div class="flex flex-1 flex-col gap-2 p-3">
            <h3
                class="line-clamp-2 min-h-10 text-sm leading-5 font-medium group-hover:underline"
            >
                {{ listing.title }}
            </h3>

            <p class="text-base font-semibold text-red-600 dark:text-red-500">
                {{ formatPrice(listing) }}
            </p>

            <div
                class="mt-auto flex flex-col gap-1 text-xs text-muted-foreground"
            >
                <!-- A fixed-price listing has no clock and no bids: just its state. -->
                <span
                    v-if="listing.sale_type === 'fixed'"
                    class="flex items-center gap-1"
                >
                    <Tag class="size-3.5 shrink-0" />
                    {{ listing.status_label }}
                </span>
                <span v-else class="flex items-center gap-1">
                    <Clock class="size-3.5 shrink-0" />
                    <template v-if="listing.status === AuctionStatus.Running">
                        Còn
                        <Countdown
                            :deadline="listing.ends_at"
                            done-label="đang chốt phiên"
                            class="font-medium text-foreground"
                        />
                    </template>
                    <template
                        v-else-if="listing.status === AuctionStatus.Upcoming"
                    >
                        Bắt đầu {{ formatDateTime(listing.starts_at) }}
                    </template>
                    <template v-else>{{ listing.status_label }}</template>
                </span>
                <span class="flex items-center justify-between gap-2">
                    <span
                        v-if="listing.sale_type !== 'fixed'"
                        class="flex items-center gap-1"
                    >
                        <Gavel class="size-3.5 shrink-0" />
                        {{ listing.bid_count }} lượt
                    </span>
                    <span
                        v-if="listing.city"
                        class="flex items-center gap-1 truncate"
                    >
                        <MapPin class="size-3.5 shrink-0" />
                        <span class="truncate">{{ listing.city }}</span>
                    </span>
                </span>
                <span v-if="listing.winner" class="truncate">
                    Người thắng:
                    <span class="font-medium text-foreground">{{
                        listing.winner
                    }}</span>
                </span>
            </div>
        </div>
    </Link>
</template>
