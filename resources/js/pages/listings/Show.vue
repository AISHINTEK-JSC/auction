<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Gavel,
    MapPin,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import BidPanel from '@/components/marketplace/BidPanel.vue';
import CommentThread from '@/components/marketplace/CommentThread.vue';
import Countdown from '@/components/marketplace/Countdown.vue';
import ImageGallery from '@/components/marketplace/ImageGallery.vue';
import ListingActions from '@/components/marketplace/ListingActions.vue';
import ListingGrid from '@/components/marketplace/ListingGrid.vue';
import SettlementPanel from '@/components/marketplace/SettlementPanel.vue';
import StarRating from '@/components/marketplace/StarRating.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { getInitials } from '@/composables/useInitials';
import { formatDateTime, formatVnd } from '@/lib/format';
import { show as categoryShow } from '@/routes/categories';
import {
    destroy as cancelBidRoute,
    index as bidsIndex,
} from '@/routes/listings/bids';
import { show as tagShow } from '@/routes/tags';
import { show as userShow } from '@/routes/users';
import { AuctionStatus, AuctionType } from '@/types';
import type {
    AuctionState,
    Bid,
    CommentItem,
    ListingCard,
    ListingDetail,
} from '@/types';

const props = defineProps<{
    listing: ListingDetail;
    auction: AuctionState;
    bids: Bid[];
    comments: CommentItem[];
    reportReasons: { value: string; label: string }[];
    sameSeller: ListingCard[];
    related: ListingCard[];
}>();

const isDown = computed(() => props.listing.auction_type === AuctionType.Down);
const isOpen = computed(
    () =>
        props.auction.status === AuctionStatus.Upcoming ||
        props.auction.status === AuctionStatus.Running,
);

// The engine has no websocket: poll its state while the auction can still change.
// When it closes, one reload of every prop picks up the winner.
const { stop } = usePoll(5000, { only: ['auction', 'bids'] });

watch(
    isOpen,
    (open, wasOpen) => {
        if (!open) {
            stop();
        }

        if (wasOpen && !open) {
            router.reload();
        }
    },
    { immediate: true },
);

function cancelBid(bid: Bid) {
    if (window.confirm(`Huỷ lượt đặt giá ${formatVnd(bid.price)} của bạn?`)) {
        router.delete(cancelBidRoute(props.listing.id).url, {
            data: { bid_price: bid.price },
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="listing.title" />

    <p
        v-if="listing.moderation !== 'approved'"
        class="mb-4 rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
        role="status"
    >
        Tin này {{ listing.moderation_label.toLowerCase() }} và không hiển thị
        với người khác.
        <template v-if="listing.moderation_note">
            Ghi chú của kiểm duyệt viên: {{ listing.moderation_note }}
        </template>
    </p>

    <!-- Two independent columns on wide screens, so a tall right column never
         leaves a gap under the photos. On phones the column wrappers dissolve
         (display: contents) and the blocks are ordered one by one: photos,
         auction, result, seller, details, bids, comments. -->
    <div
        class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start"
    >
        <div class="contents lg:flex lg:min-w-0 lg:flex-col lg:gap-6">
            <ImageGallery
                class="order-1 lg:order-none"
                :images="listing.images"
                :alt="listing.title"
            />

            <section
                class="order-5 rounded-lg border bg-background p-4 lg:order-none"
            >
                <h2 class="mb-3 text-lg font-semibold">Thông tin sản phẩm</h2>
                <dl
                    class="mb-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3"
                >
                    <div>
                        <dt class="text-muted-foreground">Tình trạng</dt>
                        <dd>{{ listing.condition }}</dd>
                    </div>
                    <div v-if="listing.category">
                        <dt class="text-muted-foreground">Danh mục</dt>
                        <dd>
                            <Link
                                :href="categoryShow(listing.category.slug)"
                                class="underline underline-offset-4"
                                >{{ listing.category.name }}</Link
                            >
                        </dd>
                    </div>
                    <div v-if="listing.city">
                        <dt class="text-muted-foreground">Khu vực</dt>
                        <dd class="flex items-center gap-1">
                            <MapPin class="size-3.5" />{{ listing.city }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Giá khởi điểm</dt>
                        <dd>{{ formatVnd(listing.start_price) }}</dd>
                    </div>
                    <div v-if="listing.bid_step">
                        <dt class="text-muted-foreground">Bước giá</dt>
                        <dd>{{ formatVnd(listing.bid_step) }}</dd>
                    </div>
                    <div v-if="listing.min_bid_price">
                        <dt class="text-muted-foreground">
                            Giá sàn (chỉ bạn thấy)
                        </dt>
                        <dd>{{ formatVnd(listing.min_bid_price) }}</dd>
                    </div>
                    <template v-if="isDown">
                        <div>
                            <dt class="text-muted-foreground">Mỗi lần giảm</dt>
                            <dd>
                                {{ formatVnd(listing.down_price) }} /
                                {{ Math.round((listing.down_time ?? 0) / 60) }}
                                phút
                            </dd>
                        </div>
                        <div v-if="listing.finish_price">
                            <dt class="text-muted-foreground">Giá thấp nhất</dt>
                            <dd>{{ formatVnd(listing.finish_price) }}</dd>
                        </div>
                    </template>
                    <div v-else-if="listing.auto_extend">
                        <dt class="text-muted-foreground">Gia hạn</dt>
                        <dd>Tự động khi có giá phút chót</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Đăng lúc</dt>
                        <dd>{{ formatDateTime(listing.created_at) }}</dd>
                    </div>
                </dl>

                <!-- Sanitized on the server (CreateListing::sanitize). -->
                <div
                    v-if="listing.description"
                    class="prose-listing text-sm leading-6"
                    v-html="listing.description"
                />

                <div
                    v-if="listing.tags.length"
                    class="mt-4 flex flex-wrap gap-2"
                >
                    <Badge
                        v-for="tag in listing.tags"
                        :key="tag.slug"
                        variant="secondary"
                        as-child
                    >
                        <Link :href="tagShow(tag.slug)">#{{ tag.name }}</Link>
                    </Badge>
                </div>
            </section>

            <section
                class="order-7 rounded-lg border bg-background p-4 lg:order-none"
            >
                <CommentThread :listing-id="listing.id" :comments="comments" />
            </section>
        </div>

        <div class="contents lg:flex lg:flex-col lg:gap-6">
            <!-- Auction panel -->
            <div
                class="order-2 space-y-4 rounded-lg border bg-background p-4 lg:order-none"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Badge :variant="isDown ? 'destructive' : 'default'">
                        <TrendingDown v-if="isDown" />
                        <TrendingUp v-else />
                        {{ listing.auction_type_label }}
                    </Badge>
                    <Badge variant="outline">{{ auction.status_label }}</Badge>
                </div>

                <h1 class="text-xl leading-7 font-semibold">
                    {{ listing.title }}
                </h1>

                <ListingActions
                    :listing="listing"
                    :report-reasons="reportReasons"
                />

                <div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            auction.status === AuctionStatus.Completed
                                ? 'Giá chốt'
                                : 'Giá hiện tại'
                        }}
                    </p>
                    <p
                        class="text-3xl font-bold text-red-600 tabular-nums dark:text-red-500"
                    >
                        {{
                            formatVnd(
                                listing.winning_price ?? auction.current_price,
                            )
                        }}
                    </p>
                    <p
                        v-if="auction.stale"
                        class="mt-1 text-xs text-amber-600"
                        role="status"
                    >
                        Chưa kết nối được hệ thống đấu giá, đây là dữ liệu gần
                        nhất.
                    </p>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div v-if="auction.status === AuctionStatus.Running">
                        <dt class="text-muted-foreground">Thời gian còn lại</dt>
                        <dd class="text-lg font-semibold">
                            <Countdown
                                :deadline="auction.remaining_time"
                                done-label="Đang chốt phiên…"
                            />
                        </dd>
                    </div>
                    <div v-else-if="auction.status === AuctionStatus.Upcoming">
                        <dt class="text-muted-foreground">Bắt đầu sau</dt>
                        <dd class="text-lg font-semibold">
                            <Countdown
                                :deadline="listing.starts_at"
                                done-label="Sắp bắt đầu…"
                            />
                        </dd>
                    </div>
                    <div v-else-if="listing.winner">
                        <dt class="text-muted-foreground">Người thắng</dt>
                        <dd class="text-lg font-semibold">
                            {{ listing.winner }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Lượt đặt giá</dt>
                        <dd>
                            <Link
                                :href="bidsIndex(listing.id)"
                                class="flex items-center gap-1 text-lg font-semibold underline-offset-4 hover:underline"
                            >
                                <Gavel class="size-4" />
                                {{ auction.bid_count }}
                            </Link>
                        </dd>
                    </div>
                </dl>

                <BidPanel
                    v-if="auction.status === AuctionStatus.Running"
                    :listing="listing"
                    :auction="auction"
                />
            </div>

            <SettlementPanel
                class="order-3 lg:order-none"
                v-if="listing.can.settle"
                :key="listing.winning_price ?? 0"
                :listing="listing"
            />

            <!-- Seller -->
            <div
                class="order-4 rounded-lg border bg-background p-4 lg:order-none"
            >
                <div class="flex items-center gap-3">
                    <Avatar class="size-11">
                        <AvatarImage
                            v-if="listing.seller.avatar"
                            :src="listing.seller.avatar"
                            :alt="listing.seller.name"
                        />
                        <AvatarFallback>{{
                            getInitials(listing.seller.name)
                        }}</AvatarFallback>
                    </Avatar>
                    <div class="min-w-0">
                        <Link
                            :href="userShow(listing.seller.id)"
                            class="block truncate font-medium underline-offset-4 hover:underline"
                        >
                            {{ listing.seller.name }}
                        </Link>
                        <p
                            v-if="listing.seller.review_count"
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <StarRating :value="listing.seller.rating" />
                            {{ listing.seller.rating }} ({{
                                listing.seller.review_count
                            }}
                            đánh giá)
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ listing.seller.listing_count }} tin đăng ·
                            {{ listing.seller.sold_count }} phiên thành công
                        </p>
                    </div>
                </div>
                <ul class="mt-3 space-y-1 text-sm text-muted-foreground">
                    <li
                        v-if="listing.seller.has_phone"
                        class="flex items-center gap-1.5"
                    >
                        <BadgeCheck class="size-4 text-green-600" />
                        Đã cung cấp số điện thoại
                    </li>
                    <li
                        v-if="listing.seller.has_address"
                        class="flex items-center gap-1.5"
                    >
                        <BadgeCheck class="size-4 text-green-600" />
                        Đã cung cấp địa chỉ
                    </li>
                </ul>
            </div>

            <section
                class="order-6 rounded-lg border bg-background p-4 lg:order-none"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Lượt đặt gần đây</h2>
                    <Link
                        :href="bidsIndex(listing.id)"
                        class="text-sm underline underline-offset-4"
                        >Xem tất cả</Link
                    >
                </div>
                <ul v-if="bids.length" class="divide-y text-sm">
                    <li
                        v-for="bid in bids"
                        :key="bid.id"
                        class="flex items-center justify-between gap-3 py-2"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium"
                                >{{ bid.bidder
                                }}<template v-if="bid.mine"> (bạn)</template>
                            </span>
                            <span class="text-xs text-muted-foreground">{{
                                formatDateTime(bid.time)
                            }}</span>
                        </span>
                        <span class="text-right">
                            <span class="block font-semibold tabular-nums">{{
                                formatVnd(bid.price)
                            }}</span>
                            <button
                                v-if="bid.can_cancel"
                                type="button"
                                class="text-xs text-muted-foreground underline underline-offset-4 hover:text-destructive"
                                @click="cancelBid(bid)"
                            >
                                Huỷ lượt này
                            </button>
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    Chưa có lượt đặt giá nào.
                </p>
            </section>
        </div>
    </div>

    <section v-if="sameSeller.length" class="mt-8 space-y-3">
        <h2 class="text-lg font-semibold">Cùng người bán</h2>
        <ListingGrid :listings="sameSeller" />
    </section>

    <section v-if="related.length" class="mt-8 space-y-3">
        <h2 class="text-lg font-semibold">Sản phẩm liên quan</h2>
        <ListingGrid :listings="related" />
    </section>
</template>
