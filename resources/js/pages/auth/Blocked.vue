<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/unlock-requests';

defineProps<{
    email: string | null;
    reason: string | null;
    sent: boolean;
}>();

defineOptions({
    layout: {
        title: 'Tài khoản đã bị khoá',
        description: 'Bạn có thể gửi yêu cầu để quản trị viên xem xét mở khoá.',
    },
});
</script>

<template>
    <Head title="Tài khoản đã bị khoá" />

    <p
        v-if="reason"
        class="mb-4 rounded-md border bg-muted/40 px-3 py-2 text-sm"
    >
        Lý do: {{ reason }}
    </p>

    <p
        v-if="sent"
        class="text-center text-sm font-medium text-green-600"
        role="status"
    >
        Đã gửi yêu cầu mở khoá. Chúng tôi sẽ phản hồi qua email.
    </p>

    <Form
        v-else
        v-bind="store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="email">Email tài khoản</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="email"
                :default-value="email ?? ''"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="description">Nội dung</Label>
            <textarea
                id="description"
                name="description"
                rows="4"
                maxlength="1000"
                placeholder="Mô tả ngắn gọn lý do bạn cần mở khoá"
                class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError :message="errors.description" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Gửi yêu cầu mở khoá
        </Button>
    </Form>

    <div class="mt-6 text-center text-sm text-muted-foreground">
        <TextLink :href="home()">Về trang chủ</TextLink>
    </div>
</template>
