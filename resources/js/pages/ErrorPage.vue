<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { index } from '@/routes/listings';

const props = defineProps<{
    status: number;
}>();

const messages: Record<number, { title: string; text: string }> = {
    403: {
        title: 'Bạn không có quyền truy cập',
        text: 'Trang này không dành cho tài khoản của bạn.',
    },
    404: {
        title: 'Không tìm thấy trang',
        text: 'Trang bạn tìm không tồn tại, đã bị gỡ hoặc phiên đấu giá chưa được hiển thị.',
    },
    429: {
        title: 'Bạn thao tác quá nhanh',
        text: 'Vui lòng chờ một lát rồi thử lại.',
    },
    500: {
        title: 'Đã có lỗi xảy ra',
        text: 'Hệ thống gặp sự cố. Vui lòng thử lại sau.',
    },
    503: {
        title: 'Hệ thống đang bảo trì',
        text: 'Chúng tôi sẽ trở lại trong ít phút.',
    },
};

const message = computed(() => messages[props.status] ?? messages[500]);
</script>

<template>
    <Head :title="message.title" />

    <div class="mx-auto flex max-w-md flex-col items-center py-16 text-center">
        <p class="text-7xl font-bold text-muted-foreground/50 tabular-nums">
            {{ status }}
        </p>
        <h1 class="mt-4 text-2xl font-semibold">{{ message.title }}</h1>
        <p class="mt-2 text-sm text-muted-foreground">{{ message.text }}</p>
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <Button as-child>
                <Link :href="home()">Về trang chủ</Link>
            </Button>
            <Button variant="outline" as-child>
                <Link :href="index()">Xem các phiên đấu giá</Link>
            </Button>
        </div>
    </div>
</template>
