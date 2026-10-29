<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { User as UserIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PhoneVerification from '@/components/PhoneVerification.vue';
import CitySelect from '@/components/marketplace/CitySelect.vue';
import SearchableSelect from '@/components/marketplace/SearchableSelect.vue';
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

const props = defineProps<{
    mustVerifyEmail: boolean;
    phoneCodePending: boolean;
    status?: string | null;
    genders: { value: string; label: string }[];
    shippingAddress: {
        name: string;
        phone: string;
        city_id: number | null;
        address: string;
    } | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

// The two pickers are not form fields themselves: hidden inputs carry their values.
const gender = ref<string | null>(user.value.gender);
const shippingCityId = ref<number | null>(
    props.shippingAddress?.city_id ?? null,
);

const today = new Date().toISOString().slice(0, 10);

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
                <p class="text-xs text-muted-foreground">
                    Mã số thành viên:
                    <span class="font-mono font-semibold text-foreground">{{
                        user.member_code
                    }}</span>
                    (dùng để tham gia quà tặng, không thay đổi)
                </p>
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
                    placeholder="09xxx"
                />
                <p class="text-xs text-muted-foreground">
                    Đổi số điện thoại thì cần xác thực lại số mới.
                </p>
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

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid content-start gap-2">
                    <Label for="gender">Giới tính</Label>
                    <SearchableSelect
                        id="gender"
                        v-model="gender"
                        :options="genders"
                        :icon="UserIcon"
                        all-label="Không muốn cho biết"
                    />
                    <input type="hidden" name="gender" :value="gender ?? ''" />
                    <InputError :message="errors.gender" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="birthday">Ngày sinh</Label>
                    <Input
                        id="birthday"
                        type="date"
                        name="birthday"
                        :default-value="user.birthday ?? ''"
                        min="1900-01-01"
                        :max="today"
                        autocomplete="bday"
                    />
                    <InputError :message="errors.birthday" />
                </div>
            </div>
            <p class="-mt-3 text-xs text-muted-foreground">
                Giới tính và ngày sinh không hiển thị với thành viên khác.
            </p>

            <fieldset class="space-y-4 rounded-lg border p-4">
                <legend class="px-1 text-sm font-medium">
                    Địa chỉ nhận hàng
                </legend>
                <p class="text-xs text-muted-foreground">
                    Chỉ người bán của phiên đấu giá bạn thắng mới thấy địa chỉ
                    này. Để trống cả bốn ô nếu bạn chưa muốn lưu.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="shipping_name">Người nhận</Label>
                        <Input
                            id="shipping_name"
                            name="shipping_name"
                            :default-value="shippingAddress?.name ?? ''"
                            autocomplete="shipping name"
                            placeholder="Họ tên người nhận"
                        />
                        <InputError :message="errors.shipping_name" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="shipping_phone"
                            >Số điện thoại nhận hàng</Label
                        >
                        <Input
                            id="shipping_phone"
                            type="tel"
                            name="shipping_phone"
                            :default-value="shippingAddress?.phone ?? ''"
                            autocomplete="shipping tel"
                            placeholder="09xxx"
                        />
                        <InputError :message="errors.shipping_phone" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="shipping_city_id">Tỉnh / thành phố</Label>
                    <CitySelect
                        id="shipping_city_id"
                        v-model="shippingCityId"
                        all-label="Chưa chọn"
                    />
                    <input
                        type="hidden"
                        name="shipping_city_id"
                        :value="shippingCityId ?? ''"
                    />
                    <InputError :message="errors.shipping_city_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="shipping_address">Địa chỉ chi tiết</Label>
                    <Input
                        id="shipping_address"
                        name="shipping_address"
                        :default-value="shippingAddress?.address ?? ''"
                        autocomplete="shipping street-address"
                        placeholder="Số nhà, đường, phường / xã, quận / huyện"
                    />
                    <InputError :message="errors.shipping_address" />
                </div>
            </fieldset>

            <div v-if="mustVerifyEmail && !user.email_verified_at">
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
                    v-if="status === 'verification-link-sent'"
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

    <PhoneVerification
        :phone="user.phone"
        :verified="!!user.phone_verified_at"
        :code-pending="phoneCodePending"
    />

    <DeleteUser />
</template>
