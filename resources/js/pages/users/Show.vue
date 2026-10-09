<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BadgeCheck } from '@lucide/vue';
import ListingGrid from '@/components/marketplace/ListingGrid.vue';
import Pagination from '@/components/marketplace/Pagination.vue';
import StarRating from '@/components/marketplace/StarRating.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { formatDateTime } from '@/lib/format';
import type { ListingCard, Paginated, ReviewItem, Seller } from '@/types';

defineProps<{
    member: Seller;
    listings: Paginated<ListingCard>;
    reviews: ReviewItem[];
}>();
</script>

<template>
    <Head :title="member.name" />

    <div class="space-y-8">
        <section
            class="flex flex-wrap items-center gap-4 rounded-lg border bg-background p-4"
        >
            <Avatar class="size-16">
                <AvatarImage
                    v-if="member.avatar"
                    :src="member.avatar"
                    :alt="member.name"
                />
                <AvatarFallback>{{ getInitials(member.name) }}</AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-2xl font-semibold">
                    {{ member.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Tham gia từ {{ formatDateTime(member.joined_at) }} ·
                    {{ member.listing_count }} tin đăng ·
                    {{ member.sold_count }} giao dịch thành công
                </p>
                <p
                    class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                >
                    <span
                        v-if="member.review_count"
                        class="flex items-center gap-1"
                    >
                        <StarRating :value="member.rating" size="md" />
                        {{ member.rating }} ({{ member.review_count }} đánh giá)
                    </span>
                    <span v-else class="text-muted-foreground"
                        >Chưa có đánh giá</span
                    >
                    <span
                        v-if="member.has_phone"
                        class="flex items-center gap-1 text-muted-foreground"
                    >
                        <BadgeCheck class="size-4 text-green-600" />
                        Đã cung cấp số điện thoại
                    </span>
                </p>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">
                Tin đấu giá ({{ listings.total }})
            </h2>
            <ListingGrid
                :listings="listings.data"
                empty="Thành viên này chưa đăng tin nào."
            />
            <Pagination :page="listings" />
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Đánh giá nhận được</h2>
            <ul v-if="reviews.length" class="space-y-3">
                <li
                    v-for="review in reviews"
                    :key="review.id"
                    class="rounded-lg border bg-background p-4"
                >
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <StarRating :value="review.rating" />
                        <span class="font-medium">{{ review.reviewer }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ formatDateTime(review.created_at) }}
                        </span>
                    </div>
                    <p
                        v-if="review.comment"
                        class="mt-1 text-sm break-words whitespace-pre-line"
                    >
                        {{ review.comment }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Phiên: {{ review.listing }}
                    </p>
                </li>
            </ul>
            <p
                v-else
                class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Chưa có đánh giá nào.
            </p>
        </section>
    </div>
</template>
