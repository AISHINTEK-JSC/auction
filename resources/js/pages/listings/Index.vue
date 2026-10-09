<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, BellRing } from '@lucide/vue';
import { computed, reactive } from 'vue';
import CitySelect from '@/components/marketplace/CitySelect.vue';
import ListingGrid from '@/components/marketplace/ListingGrid.vue';
import Pagination from '@/components/marketplace/Pagination.vue';
import { Button } from '@/components/ui/button';
import { show as categoryShow } from '@/routes/categories';
import { index } from '@/routes/listings';
import {
    destroy as destroySavedSearch,
    store as storeSavedSearch,
} from '@/routes/saved-searches';
import { show as tagShow } from '@/routes/tags';
import type { ListingCard, Paginated } from '@/types';

type Filters = {
    q: string;
    city_id: number | null;
    type: number | null;
    status: 'open' | 'running' | 'upcoming' | 'ended';
    sort: 'ending' | 'newest' | 'popular' | 'price_asc' | 'price_desc';
};

const props = defineProps<{
    listings: Paginated<ListingCard>;
    category: { id: number; name: string; slug: string } | null;
    tag: { name: string; slug: string } | null;
    filters: Filters;
    /** Null for guests and on tag pages, where a search cannot be saved. */
    savedSearch: {
        criteria: Record<string, string | number | null>;
        can_save: boolean;
        id: number | null;
    } | null;
}>();

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
        : 'Tất cả tin đăng';
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

function saveSearch() {
    if (props.savedSearch) {
        router.post(storeSavedSearch().url, props.savedSearch.criteria, {
            preserveScroll: true,
        });
    }
}

function forgetSearch() {
    if (props.savedSearch?.id) {
        router.delete(destroySavedSearch(props.savedSearch.id).url, {
            preserveScroll: true,
        });
    }
}

const selectClass = 'h-9 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head :title="title" />

    <div class="space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h1 class="text-2xl font-semibold">{{ title }}</h1>
            <div class="flex items-center gap-3">
                <p class="text-sm text-muted-foreground">
                    {{ listings.total }} kết quả
                </p>
                <template v-if="savedSearch?.can_save">
                    <Button
                        v-if="savedSearch.id"
                        variant="outline"
                        size="sm"
                        aria-pressed="true"
                        @click="forgetSearch"
                    >
                        <BellRing class="size-4 text-amber-500" />
                        Đang nhận tin mới
                    </Button>
                    <Button
                        v-else
                        variant="outline"
                        size="sm"
                        aria-pressed="false"
                        @click="saveSearch"
                    >
                        <Bell class="size-4" />
                        Báo khi có tin mới
                    </Button>
                </template>
            </div>
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

            <label class="sr-only" for="filter-type">Hình thức bán</label>
            <select id="filter-type" v-model="form.type" :class="selectClass">
                <option :value="null">Mọi hình thức</option>
                <option :value="1">Đấu giá xuôi</option>
                <option :value="2">Đấu giá ngược</option>
                <option :value="3">Giá cố định</option>
            </select>

            <label class="sr-only" for="filter-city">Khu vực</label>
            <div class="w-48">
                <!-- Not a native control: the form's change event does not fire for it. -->
                <CitySelect
                    id="filter-city"
                    v-model="form.city_id"
                    all-label="Toàn quốc"
                    @update:model-value="apply"
                />
            </div>

            <label class="sr-only" for="filter-sort">Sắp xếp</label>
            <select
                id="filter-sort"
                v-model="form.sort"
                :class="[selectClass, 'ml-auto']"
            >
                <option value="ending">Sắp kết thúc</option>
                <option value="newest">Mới đăng</option>
                <option value="popular">Xem nhiều nhất</option>
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
