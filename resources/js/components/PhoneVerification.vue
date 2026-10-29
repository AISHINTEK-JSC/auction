<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { BadgeCheck } from '@lucide/vue';
import { ref } from 'vue';
import PhoneVerificationController from '@/actions/App/Http/Controllers/Settings/PhoneVerificationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    /** The number saved on the profile, not the one being typed in the form above. */
    phone: string | null;
    verified: boolean;
    /** A code was texted and can still be typed in. */
    codePending: boolean;
}>();

const code = ref('');
</script>

<template>
    <section class="space-y-4 rounded-lg border p-4">
        <div class="space-y-1">
            <h2 class="text-sm font-medium">Xác thực số điện thoại</h2>
            <p class="text-xs text-muted-foreground">
                Cần xác thực để đặt giá và đăng tin. Chúng tôi gửi một mã 6 chữ
                số qua SMS tới số trong hồ sơ của bạn.
            </p>
        </div>

        <p v-if="!phone" class="text-sm text-muted-foreground">
            Nhập số điện thoại ở trên và bấm Lưu, rồi quay lại đây để xác thực.
        </p>

        <p
            v-else-if="verified"
            class="flex items-center gap-2 text-sm font-medium text-green-600"
            data-test="phone-verified"
        >
            <BadgeCheck class="size-4" />
            Số {{ phone }} đã được xác thực.
        </p>

        <template v-else>
            <Form
                v-if="codePending"
                v-bind="PhoneVerificationController.update.form()"
                reset-on-error
                @finish="code = ''"
                class="space-y-3"
                v-slot="{ errors, processing }"
            >
                <p class="text-sm">
                    Nhập mã vừa gửi tới số <strong>{{ phone }}</strong
                    >.
                </p>
                <input type="hidden" name="code" :value="code" />
                <InputOTP
                    id="phone-code"
                    v-model="code"
                    :maxlength="6"
                    :disabled="processing"
                    autocomplete="one-time-code"
                    inputmode="numeric"
                >
                    <InputOTPGroup>
                        <InputOTPSlot
                            v-for="index in 6"
                            :key="index"
                            :index="index - 1"
                        />
                    </InputOTPGroup>
                </InputOTP>
                <InputError :message="errors.code" />
                <Button :disabled="processing || code.length < 6">
                    <Spinner v-if="processing" />
                    Xác nhận
                </Button>
            </Form>

            <Form
                v-bind="PhoneVerificationController.store.form()"
                v-slot="{ errors, processing }"
                class="space-y-2"
            >
                <p v-if="!codePending" class="text-sm">
                    Số <strong>{{ phone }}</strong> chưa được xác thực.
                </p>
                <Button
                    :variant="codePending ? 'outline' : 'default'"
                    :disabled="processing"
                    data-test="send-phone-code"
                >
                    <Spinner v-if="processing" />
                    {{ codePending ? 'Gửi lại mã' : 'Gửi mã xác thực' }}
                </Button>
                <InputError v-if="!codePending" :message="errors.code" />
            </Form>
        </template>
    </section>
</template>
