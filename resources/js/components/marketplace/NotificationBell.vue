<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, BellOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/format';
import { index, readAll, show } from '@/routes/notifications';

/**
 * The bell in the header. Opening it drops down the latest notifications,
 * fetched at that moment; choosing one marks it read and goes to its target.
 */
const page = usePage();
const unread = computed(() => page.props.unreadNotifications ?? 0);
const items = computed(() => page.props.recentNotifications);

const open = ref(false);
const loading = ref(false);

const PROPS = ['recentNotifications', 'unreadNotifications'];

function onOpenChange(isOpen: boolean) {
    open.value = isOpen;

    if (isOpen) {
        loading.value = items.value === undefined;

        router.reload({
            only: PROPS,
            onFinish: () => (loading.value = false),
        });
    }
}

function markAllRead() {
    router.post(readAll().url, {}, { preserveScroll: true, only: PROPS });
}
</script>

<template>
    <DropdownMenu :open="open" @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative size-9 shrink-0"
                :aria-label="
                    unread ? `Thông báo, ${unread} chưa đọc` : 'Thông báo'
                "
            >
                <Bell class="size-5" />
                <span
                    v-if="unread"
                    class="absolute -top-0.5 -right-0.5 flex min-w-4.5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-4.5 font-semibold text-white"
                >
                    {{ unread > 99 ? '99+' : unread }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :collision-padding="12"
            class="w-96 max-w-[calc(100vw-1.5rem)] p-0"
        >
            <div
                class="flex items-center justify-between gap-3 border-b px-4 py-3"
            >
                <p class="font-semibold">Thông báo</p>
                <button
                    v-if="unread"
                    type="button"
                    class="text-xs text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    @click="markAllRead"
                >
                    Đánh dấu đã đọc tất cả
                </button>
            </div>

            <div class="max-h-[min(26rem,70vh)] overflow-y-auto">
                <div
                    v-if="loading"
                    class="space-y-3 p-4"
                    role="status"
                    aria-label="Đang tải thông báo"
                >
                    <div
                        v-for="row in 3"
                        :key="row"
                        class="h-10 animate-pulse rounded-md bg-muted"
                    />
                </div>

                <div
                    v-else-if="!items?.length"
                    class="flex flex-col items-center gap-2 px-4 py-10 text-sm text-muted-foreground"
                >
                    <BellOff class="size-6" />
                    Bạn chưa có thông báo nào.
                </div>

                <template v-else>
                    <DropdownMenuItem
                        v-for="item in items"
                        :key="item.id"
                        as-child
                        class="cursor-pointer rounded-none border-b px-4 py-3 last:border-b-0"
                    >
                        <!-- The server marks it read, then redirects to its target. -->
                        <Link
                            :href="show(item.id)"
                            class="flex items-start gap-3"
                        >
                            <span
                                class="mt-1.5 size-2 shrink-0 rounded-full"
                                :class="
                                    item.read ? 'bg-transparent' : 'bg-red-500'
                                "
                                :aria-label="item.read ? undefined : 'Chưa đọc'"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm"
                                    :class="
                                        item.read
                                            ? 'font-normal'
                                            : 'font-semibold'
                                    "
                                >
                                    {{ item.title }}
                                </span>
                                <span
                                    class="mt-0.5 line-clamp-2 block text-xs text-muted-foreground"
                                >
                                    {{ item.body }}
                                </span>
                                <span
                                    class="mt-1 block text-[11px] text-muted-foreground"
                                >
                                    {{ formatDateTime(item.created_at) }}
                                </span>
                            </span>
                        </Link>
                    </DropdownMenuItem>
                </template>
            </div>

            <div class="border-t p-2 text-center">
                <Link
                    :href="index()"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    @click="open = false"
                >
                    Xem tất cả thông báo
                </Link>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
