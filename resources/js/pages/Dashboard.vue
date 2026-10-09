<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import AuctionCard from '@/components/marketplace/AuctionCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    create,
    destroy,
    edit,
    relist,
    renew as renewRoute,
    sold,
} from '@/routes/listings';
import { edit as editDraft } from '@/routes/listings/drafts';
import { AuctionStatus } from '@/types';
import type { DashboardCard, Seller } from '@/types';

type Tab =
    | 'selling'
    | 'bidding'
    | 'watching'
    | 'won'
    | 'ended'
    | 'drafts'
    | 'review';

const props = defineProps<{
    seller: Seller;
    selling: DashboardCard[];
    bidding: DashboardCard[];
    watching: DashboardCard[];
    won: DashboardCard[];
    ended: DashboardCard[];
    drafts: DashboardCard[];
    review: DashboardCard[];
}>();

const tabs: { key: Tab; label: string; empty: string }[] = [
    {
        key: 'selling',
        label: 'Đang bán',
        empty: 'Bạn chưa có phiên đấu giá nào đang mở.',
    },
    {
        key: 'bidding',
        label: 'Đang tham gia',
        empty: 'Bạn chưa đặt giá ở phiên nào đang diễn ra.',
    },
    {
        key: 'watching',
        label: 'Theo dõi',
        empty: 'Bạn chưa theo dõi phiên nào. Bấm "Theo dõi" ở trang phiên đấu giá.',
    },
    { key: 'won', label: 'Đã thắng', empty: 'Bạn chưa thắng phiên nào.' },
    {
        key: 'ended',
        label: 'Đã kết thúc',
        empty: 'Chưa có phiên nào của bạn kết thúc.',
    },
    {
        key: 'drafts',
        label: 'Nháp',
        empty: 'Bạn không có tin nháp nào.',
    },
    {
        key: 'review',
        label: 'Chờ duyệt',
        empty: 'Không có tin nào đang chờ duyệt hoặc bị từ chối.',
    },
];

// "Nháp" and "Chờ duyệt" only earn a tab when there is something in them.
const visibleTabs = computed(() =>
    tabs.filter(
        (tab) =>
            (tab.key !== 'review' && tab.key !== 'drafts') ||
            props[tab.key].length > 0,
    ),
);

const active = ref<Tab>('selling');
const listings = computed(() => props[active.value]);
const activeTab = computed(() => tabs.find((tab) => tab.key === active.value)!);

function cancel(listing: DashboardCard) {
    const question =
        listing.sale_type === 'fixed'
            ? `Gỡ tin “${listing.title}”?`
            : `Huỷ phiên đấu giá “${listing.title}”?`;

    if (window.confirm(question)) {
        router.delete(destroy(listing.id).url, { preserveScroll: true });
    }
}

function renew(listing: DashboardCard) {
    router.post(renewRoute(listing.id).url, {}, { preserveScroll: true });
}

function markSold(listing: DashboardCard) {
    if (window.confirm(`Đánh dấu “${listing.title}” là đã bán?`)) {
        router.post(sold(listing.id).url, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Tin đăng của tôi" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Tin đăng của tôi</h1>
                <p class="text-sm text-muted-foreground">
                    {{ seller.listing_count }} tin đã đăng ·
                    {{ seller.sold_count }} giao dịch thành công
                    <template v-if="seller.review_count">
                        · {{ seller.rating }}/5 từ
                        {{ seller.review_count }} đánh giá
                    </template>
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Đăng tin
                </Link>
            </Button>
        </div>

        <div class="-mx-4 overflow-x-auto px-4">
            <div class="flex min-w-max gap-1 border-b" role="tablist">
                <button
                    v-for="tab in visibleTabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="active === tab.key"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap"
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

                <Badge
                    v-if="
                        active === 'bidding' &&
                        listing.status === AuctionStatus.Running
                    "
                    :variant="listing.leading ? 'default' : 'destructive'"
                    class="w-full"
                >
                    {{
                        listing.leading
                            ? 'Bạn đang dẫn đầu'
                            : 'Bạn đã bị vượt giá'
                    }}
                </Badge>

                <p
                    v-if="active === 'review' && listing.moderation_note"
                    class="text-xs text-muted-foreground"
                >
                    Ghi chú: {{ listing.moderation_note }}
                </p>

                <Button
                    v-if="active === 'drafts'"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="editDraft(listing.id)">Sửa tiếp</Link>
                </Button>

                <div
                    v-else-if="
                        active === 'selling' ||
                        active === 'ended' ||
                        active === 'review'
                    "
                    class="flex flex-wrap gap-1"
                >
                    <Button
                        v-if="listing.can.edit"
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        as-child
                    >
                        <Link :href="edit(listing.id)">Sửa</Link>
                    </Button>
                    <Button
                        v-if="listing.can.cancel"
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        @click="cancel(listing)"
                    >
                        {{
                            listing.sale_type === 'fixed'
                                ? 'Gỡ tin'
                                : 'Huỷ phiên'
                        }}
                    </Button>
                    <Button
                        v-if="listing.can.mark_sold"
                        size="sm"
                        class="flex-1"
                        @click="markSold(listing)"
                    >
                        Đã bán
                    </Button>
                    <Button
                        v-if="listing.can.renew"
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        @click="renew(listing)"
                    >
                        Gia hạn
                    </Button>
                    <Button
                        v-if="listing.can.relist"
                        size="sm"
                        class="flex-1"
                        as-child
                    >
                        <Link :href="relist(listing.id)">Đăng lại</Link>
                    </Button>
                </div>
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
