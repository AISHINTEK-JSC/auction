<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import CaptchaWidget from '@/components/marketplace/CaptchaWidget.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { privacy, terms } from '@/routes/legal';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

const captcha = ref<InstanceType<typeof CaptchaWidget> | null>(null);

defineOptions({
    layout: {
        title: 'Tạo tài khoản',
        description: 'Điền thông tin bên dưới để đăng ký',
    },
});
</script>

<template>
    <Head title="Đăng ký" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        @error="captcha?.reset()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Họ tên</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Họ và tên"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Mật khẩu</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Mật khẩu"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Nhập lại mật khẩu</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Nhập lại mật khẩu"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <!-- Adds its own hidden field to the form; a token works once, hence the reset on error. -->
            <CaptchaWidget
                ref="captcha"
                :error="errors['cf-turnstile-response']"
            />

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Tạo tài khoản
            </Button>

            <p class="text-center text-xs text-muted-foreground">
                Khi tạo tài khoản, bạn đồng ý với
                <TextLink :href="terms()" class="underline underline-offset-4"
                    >Điều khoản sử dụng</TextLink
                >
                và
                <TextLink :href="privacy()" class="underline underline-offset-4"
                    >Chính sách quyền riêng tư</TextLink
                >.
            </p>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Đã có tài khoản?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Đăng nhập</TextLink
            >
        </div>
    </Form>
</template>
