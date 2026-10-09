<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import ListingGrid from '@/components/marketplace/ListingGrid.vue';
import Pagination from '@/components/marketplace/Pagination.vue';
import { show as categoryShow } from '@/routes/categories';
import { index } from '@/routes/listings';
import { show as tagShow } from '@/routes/tags';
import type { ListingCard, Paginated } from '@/types';

type Filters = {
    q: string;
    city_id: number | null;
    type: number | null;
    status: 'open' | 'running' | 'upcoming' | 'ended';
    sort: 'ending' | 'newest' | 'price_asc' | 'price_desc';
};

const props = defineProps<{
    listings: Paginated<ListingCard>;
    category: { id: number; name: string; slug: string } | null;
    tag: { name: string; slug: string } | null;
    filters: Filters;
}>();

const cities = computed(() => usePage().props.cities);
const form = reactive({ ...props.filters });

const title = computed(() => {
    if (props.category) {
        return props.category.name;
    }

    if (props.tag) {
        return `#${props.tag.name}`;
    }

    return props.filters.q
        ? `Kết quả cho “${props.filters.q}”`
        : 'Tất cả phiên đấu giá';
});

function apply() {
    const query = {
        q: form.q || undefined,
        city_id: form.city_id ?? undefined,
        type: form.type ?? undefined,
        status: form.status === 'open' ? undefined : form.status,
        sort: form.sort,
    };

    router.get(
        props.category
            ? categoryShow(props.category.slug, { query }).url
            : props.tag
              ? tagShow(props.tag.slug, { query }).url
              : index({ query }).url,
        {},
        { preserveState: true, preserveScroll: true },
    );
}

const selectClass = 'h-9 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head :title="title" />

    <div class="space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h1 class="text-2xl font-semibold">{{ title }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ listings.total }} kết quả
            </p>
        </div>

        <form
            class="flex flex-wrap items-center gap-2 rounded-lg border bg-background p-3"
            @change="apply"
            @submit.prevent="apply"
        >
            <label class="sr-only" for="filter-status">Trạng thái</label>
            <select
                id="filter-status"
                v-model="form.status"
                :class="selectClass"
            >
                <option value="open">Đang và sắp diễn ra</option>
                <option value="running">Đang diễn ra</option>
                <option value="upcoming">Sắp diễn ra</option>
                <option value="ended">Đã kết thúc</option>
            </select>

            <label class="sr-only" for="filter-type">Loại đấu giá</label>
            <select id="filter-type" v-model="form.type" :class="selectClass">
                <option :value="null">Mọi loại đấu giá</option>
                <option :value="1">Đấu giá xuôi</option>
                <option :value="2">Đấu giá ngược</option>
            </select>

            <label class="sr-only" for="filter-city">Khu vực</label>
            <select
                id="filter-city"
                v-model="form.city_id"
                :class="selectClass"
            >
                <option :value="null">Toàn quốc</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">
                    {{ city.name }}
                </option>
            </select>

            <label class="sr-only" for="filter-sort">Sắp xếp</label>
            <select
                id="filter-sort"
                v-model="form.sort"
                :class="[selectClass, 'ml-auto']"
            >
                <option value="ending">Sắp kết thúc</option>
                <option value="newest">Mới đăng</option>
                <option value="price_asc">Giá tăng dần</option>
                <option value="price_desc">Giá giảm dần</option>
            </select>
        </form>

        <ListingGrid
            :listings="listings.data"
            empty="Không tìm thấy phiên đấu giá phù hợp."
        />
        <Pagination :page="listings" />
    </div>
</template>
