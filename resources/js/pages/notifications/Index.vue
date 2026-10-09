<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Pagination from '@/components/marketplace/Pagination.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { readAll, show } from '@/routes/notifications';
import type { NotificationItem, Paginated } from '@/types';

defineProps<{
    notifications: Paginated<NotificationItem>;
}>();

function markAllRead() {
    router.post(readAll().url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Thông báo" />

    <div class="mx-auto max-w-3xl space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Thông báo</h1>
            <Button
                v-if="notifications.data.some((item) => !item.read)"
                variant="outline"
                size="sm"
                @click="markAllRead"
            >
                Đánh dấu đã đọc tất cả
            </Button>
        </div>

        <ul v-if="notifications.data.length" class="space-y-2">
            <li v-for="item in notifications.data" :key="item.id">
                <!-- A plain link: the server marks it read, then redirects to its target. -->
                <a
                    :href="show(item.id).url"
                    class="block rounded-lg border bg-background p-4 hover:bg-accent"
                    :class="{ 'border-primary/50': !item.read }"
                >
                    <span class="flex items-start justify-between gap-3">
                        <span class="font-medium">
                            <span
                                v-if="!item.read"
                                class="mr-1.5 inline-block size-2 rounded-full bg-red-500 align-middle"
                                aria-label="Chưa đọc"
                            />
                            {{ item.title }}
                        </span>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ formatDateTime(item.created_at) }}
                        </span>
                    </span>
                    <span class="mt-1 block text-sm text-muted-foreground">
                        {{ item.body }}
                    </span>
                </a>
            </li>
        </ul>
        <p
            v-else
            class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
        >
            Bạn chưa có thông báo nào.
        </p>

        <Pagination :page="notifications" />
    </div>
</template>
