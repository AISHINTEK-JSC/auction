<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Xác thực email',
        description:
            'Hãy bấm vào liên kết chúng tôi vừa gửi vào email của bạn để xác thực tài khoản.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Xác thực email" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Liên kết xác thực mới đã được gửi tới email bạn dùng khi đăng ký.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Gửi lại email xác thực
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Đăng xuất
        </TextLink>
    </Form>
</template>
