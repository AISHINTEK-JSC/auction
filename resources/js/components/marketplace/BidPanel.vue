<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import MoneyInput from '@/components/marketplace/MoneyInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAuthUser } from '@/composables/useAuthUser';
import { formatDateTime, formatVnd } from '@/lib/format';
import { login } from '@/routes';
import { store } from '@/routes/listings/bids';
import { AuctionType, BidType } from '@/types';
import type { AuctionState, ListingDetail } from '@/types';

const props = defineProps<{
    listing: ListingDetail;
    auction: AuctionState;
}>();

const user = useAuthUser();
const isDown = computed(() => props.listing.auction_type === AuctionType.Down);

// The server refuses bids from a banned member; say so instead of showing the form.
const bannedUntil = computed(() => {
    const until = user.value?.bid_banned_until;

    return until && new Date(until).getTime() > Date.now() ? until : null;
});

const form = useForm<{ bid_type: number; bid_price: number | '' }>({
    bid_type: BidType.Limit,
    bid_price: '',
});

const hasBids = computed(() => props.auction.highest_bidder_id !== null);

// What one quick bid adds. The seller's step when there is one; otherwise the
// engine's own rule (AuctionModel::computeAlpha), which it applies to quick
// bids only: a typed bid then just has to beat the current price.
const step = computed(() => {
    if (props.listing.bid_step) {
        return props.listing.bid_step;
    }

    const digits = String(props.auction.current_price).length;

    return digits >= 5 ? 100 : digits === 4 ? 10 : 1;
});

// Lowest price the server accepts when the seller set a step (ListingBidController).
const minimum = computed(
    () => props.auction.current_price + (hasBids.value ? step.value : 0),
);

const hint = computed(() => {
    if (isDown.value) {
        return `Giá chờ phải thấp hơn hoặc bằng ${formatVnd(props.auction.current_price)} và chia hết cho ${formatVnd(props.listing.down_price)}.`;
    }

    if (props.listing.bid_step && hasBids.value) {
        return `Giá đặt tối thiểu ${formatVnd(minimum.value)} (bước giá ${formatVnd(step.value)}).`;
    }

    return hasBids.value
        ? `Giá đặt phải cao hơn ${formatVnd(props.auction.current_price)}.`
        : `Giá đặt tối thiểu ${formatVnd(props.auction.current_price)}.`;
});

function submit(type: number) {
    form.bid_type = type;
    form.post(store(props.listing.id).url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <div v-if="!user" class="rounded-lg border bg-muted/40 p-4 text-sm">
        <Link :href="login()" class="font-medium underline underline-offset-4"
            >Đăng nhập</Link
        >
        để tham gia đấu giá.
    </div>

    <div
        v-else-if="bannedUntil"
        class="rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm"
        role="status"
    >
        Bạn bị tạm khoá đặt giá tới {{ formatDateTime(bannedUntil) }} vì đã từ
        bỏ kết quả đấu giá nhiều lần.
    </div>

    <div
        v-else-if="listing.is_owner"
        class="rounded-lg border bg-muted/40 p-4 text-sm text-muted-foreground"
    >
        Đây là phiên đấu giá của bạn.
    </div>

    <form v-else class="space-y-3" @submit.prevent="submit(BidType.Limit)">
        <div class="grid gap-2">
            <Label for="bid_price">{{
                isDown ? 'Đặt giá chờ' : 'Giá bạn muốn đặt'
            }}</Label>
            <div class="flex gap-2">
                <MoneyInput
                    id="bid_price"
                    v-model="form.bid_price"
                    required
                    class="min-w-0 flex-1"
                    placeholder="Nhập số tiền"
                />
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Đặt giá
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">{{ hint }}</p>
            <InputError :message="form.errors.bid_price" />
        </div>

        <!-- Without a seller's step, the engine places an opening quick bid at the
             hidden reserve price, so the buyer could not know the amount: no
             quick bid until someone has bid. With a step the app sets the price. -->
        <Button
            v-if="isDown || hasBids || listing.bid_step"
            type="button"
            variant="outline"
            class="w-full"
            :disabled="form.processing"
            @click="submit(BidType.Market)"
        >
            <template v-if="isDown">
                Mua ngay với giá {{ formatVnd(auction.current_price) }}
            </template>
            <template v-else-if="listing.bid_step">
                Đặt nhanh {{ formatVnd(minimum) }}
            </template>
            <template v-else>Đặt nhanh +{{ formatVnd(step) }}</template>
        </Button>
    </form>
</template>
