<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import GiveawayCard from '@/components/marketplace/GiveawayCard.vue';
import Pagination from '@/components/marketplace/Pagination.vue';
import type { Giveaway, Paginated } from '@/types';

defineProps<{
    giveaways: Paginated<Giveaway>;
}>();
</script>

<template>
    <Head title="Quà tặng" />

    <div class="space-y-4">
        <div>
            <h1 class="text-2xl font-semibold">Quà tặng</h1>
            <p class="text-sm text-muted-foreground">
                Đăng ký tham gia miễn phí bằng mã số thành viên của bạn. Mỗi
                thành viên một lượt, người trúng được chọn ngẫu nhiên.
            </p>
        </div>

        <div
            v-if="giveaways.data.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <GiveawayCard
                v-for="giveaway in giveaways.data"
                :key="giveaway.id"
                :giveaway="giveaway"
            />
        </div>
        <p
            v-else
            class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
        >
            Hiện chưa có chương trình tặng quà nào.
        </p>

        <Pagination :page="giveaways" />
    </div>
</template>
