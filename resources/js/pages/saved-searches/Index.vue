<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BellRing, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index as searchIndex } from '@/routes/listings';
import { destroy } from '@/routes/saved-searches';

defineProps<{
    searches: {
        id: number;
        label: string;
        url: string;
        created_at: string | null;
    }[];
}>();

function remove(id: number) {
    router.delete(destroy(id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Tìm kiếm đã lưu" />

    <div class="mx-auto max-w-3xl space-y-4">
        <div>
            <h1 class="text-2xl font-semibold">Tìm kiếm đã lưu</h1>
            <p class="text-sm text-muted-foreground">
                Bạn được báo qua chuông và email mỗi khi có tin mới khớp một
                trong các tìm kiếm dưới đây.
            </p>
        </div>

        <ul v-if="searches.length" class="space-y-2">
            <li
                v-for="search in searches"
                :key="search.id"
                class="flex items-center gap-3 rounded-lg border bg-background p-4"
            >
                <BellRing class="size-5 shrink-0 text-amber-500" />
                <div class="min-w-0 flex-1">
                    <Link
                        :href="search.url"
                        class="block truncate font-medium underline-offset-4 hover:underline"
                    >
                        {{ search.label }}
                    </Link>
                    <p class="text-xs text-muted-foreground">
                        Lưu lúc {{ formatDateTime(search.created_at) }}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="`Xoá tìm kiếm ${search.label}`"
                    @click="remove(search.id)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </li>
        </ul>
        <div
            v-else
            class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
        >
            <p>Bạn chưa lưu tìm kiếm nào.</p>
            <p class="mt-1">
                Vào
                <Link :href="searchIndex()" class="underline underline-offset-4"
                    >trang tìm kiếm</Link
                >, chọn từ khoá, danh mục hoặc khu vực rồi bấm "Báo khi có tin
                mới".
            </p>
        </div>
    </div>
</template>
