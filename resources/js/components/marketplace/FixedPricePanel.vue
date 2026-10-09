<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Mail, MapPin, Phone, RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAuthUser } from '@/composables/useAuthUser';
import { login } from '@/routes';
import { formatDateTime } from '@/lib/format';
import { destroy, renew as renewRoute, sold } from '@/routes/listings';
import { send as resendVerification } from '@/routes/verification';
import { AuctionStatus } from '@/types';
import type { ListingDetail } from '@/types';

/**
 * What a fixed-price listing shows instead of the bidding panel: how to reach
 * the seller, or, for the seller, the buttons that close the listing.
 */
const props = defineProps<{
    listing: ListingDetail;
}>();

const user = useAuthUser();

function markSold() {
    if (
        window.confirm(
            'Đánh dấu tin này là đã bán? Tin sẽ không còn nhận liên hệ.',
        )
    ) {
        router.post(sold(props.listing.id).url, {}, { preserveScroll: true });
    }
}

function renew() {
    router.post(renewRoute(props.listing.id).url, {}, { preserveScroll: true });
}

function takeDown() {
    if (window.confirm('Gỡ tin này khỏi sàn?')) {
        router.delete(destroy(props.listing.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <!-- Seller -->
    <div v-if="listing.is_owner" class="space-y-2">
        <p class="text-sm text-muted-foreground">
            Đây là tin của bạn. Người mua sẽ liên hệ qua email và số điện thoại
            trong hồ sơ của bạn.
        </p>
        <div class="flex flex-wrap gap-2">
            <Button v-if="listing.can.mark_sold" @click="markSold">
                Đánh dấu đã bán
            </Button>
            <Button v-if="listing.can.renew" variant="outline" @click="renew">
                <RefreshCw class="size-4" />
                Gia hạn
            </Button>
            <Button
                v-if="listing.can.cancel"
                variant="outline"
                @click="takeDown"
            >
                Gỡ tin
            </Button>
        </div>
        <p
            v-if="listing.can.renew && listing.ends_at"
            class="text-xs text-muted-foreground"
        >
            {{
                listing.status === AuctionStatus.Expired
                    ? 'Tin đã hết hạn.'
                    : `Tin hiển thị đến ${formatDateTime(listing.ends_at)}.`
            }}
            Gia hạn để tính lại thời gian hiển thị từ hôm nay.
        </p>
    </div>

    <!-- No longer for sale -->
    <p
        v-else-if="
            listing.status !== AuctionStatus.Running &&
            listing.status !== AuctionStatus.Upcoming
        "
        class="rounded-lg border bg-muted/40 p-4 text-sm text-muted-foreground"
    >
        Tin này không còn bán nữa.
    </p>

    <!-- Buyer who may see the contact details -->
    <div
        v-else-if="listing.contact"
        class="space-y-2 rounded-lg border bg-muted/40 p-4 text-sm"
    >
        <p class="font-medium">Liên hệ người bán để mua</p>
        <p class="flex items-center gap-2">
            <Phone class="size-4 shrink-0 text-muted-foreground" />
            <a
                v-if="listing.contact.phone"
                :href="`tel:${listing.contact.phone}`"
                class="text-base font-semibold underline underline-offset-4"
                >{{ listing.contact.phone }}</a
            >
            <span v-else class="text-muted-foreground"
                >Người bán chưa cung cấp số điện thoại</span
            >
        </p>
        <p class="flex items-center gap-2">
            <Mail class="size-4 shrink-0 text-muted-foreground" />
            <a
                :href="`mailto:${listing.contact.email}`"
                class="truncate underline underline-offset-4"
                >{{ listing.contact.email }}</a
            >
        </p>
        <p v-if="listing.contact.address" class="flex items-center gap-2">
            <MapPin class="size-4 shrink-0 text-muted-foreground" />
            {{ listing.contact.address }}
        </p>
    </div>

    <!-- Signed in but not verified -->
    <div
        v-else-if="user"
        class="rounded-lg border bg-muted/40 p-4 text-sm text-muted-foreground"
    >
        Hãy xác thực email để xem thông tin liên hệ của người bán.
        <Link
            :href="resendVerification()"
            as="button"
            class="font-medium text-foreground underline underline-offset-4"
            >Gửi lại email xác thực</Link
        >
    </div>

    <!-- Guest -->
    <div v-else class="rounded-lg border bg-muted/40 p-4 text-sm">
        <Link :href="login()" class="font-medium underline underline-offset-4"
            >Đăng nhập</Link
        >
        để xem thông tin liên hệ của người bán.
    </div>
</template>
