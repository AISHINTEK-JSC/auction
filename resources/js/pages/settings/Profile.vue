<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { avatar, edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Hồ sơ',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

function uploadAvatar(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        router.post(avatar().url, { avatar: file }, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Hồ sơ" />

    <h1 class="sr-only">Hồ sơ</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Hồ sơ"
            description="Cập nhật thông tin hiển thị với người mua và người bán"
        />

        <div class="flex items-center gap-4">
            <Avatar class="size-16">
                <AvatarImage
                    v-if="user.avatar"
                    :src="user.avatar"
                    :alt="user.name"
                />
                <AvatarFallback>{{ getInitials(user.name) }}</AvatarFallback>
            </Avatar>
            <div class="grid gap-1">
                <Label
                    for="avatar"
                    class="cursor-pointer underline underline-offset-4"
                    >Đổi ảnh đại diện</Label
                >
                <input
                    id="avatar"
                    type="file"
                    accept="image/*"
                    class="sr-only"
                    @change="uploadAvatar"
                />
                <p class="text-xs text-muted-foreground">Ảnh ≤ 2MB.</p>
                <InputError :message="page.props.errors?.avatar" />
            </div>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Họ tên</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Họ và tên"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Số điện thoại</Label>
                <Input
                    id="phone"
                    type="tel"
                    class="mt-1 block w-full"
                    name="phone"
                    :default-value="user.phone ?? ''"
                    autocomplete="tel"
                    placeholder="0912345678"
                />
                <InputError class="mt-2" :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Địa chỉ</Label>
                <Input
                    id="address"
                    class="mt-1 block w-full"
                    name="address"
                    :default-value="user.address ?? ''"
                    autocomplete="street-address"
                />
                <InputError class="mt-2" :message="errors.address" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Email của bạn chưa được xác thực.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Gửi lại email xác thực.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Đã gửi liên kết xác thực mới vào email của bạn.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Lưu</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
