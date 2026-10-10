<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Mail, MapPin, Phone, Truck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import StarRating from '@/components/marketplace/StarRating.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { confirm } from '@/composables/useConfirm';
import { formatVnd } from '@/lib/format';
import { withdrawWinner } from '@/routes/listings';
import { store as review } from '@/routes/listings/reviews';
import type { ListingDetail } from '@/types';

/**
 * Shown to the seller and the winner once the auction is over: how to reach
 * each other, a way out if the winner does not buy, and the review form.
 */
const props = defineProps<{
    listing: ListingDetail;
}>();

const form = useForm({
    rating: props.listing.my_review?.rating ?? 0,
    comment: props.listing.my_review?.comment ?? '',
});

function submitReview() {
    form.post(review(props.listing.id).url, { preserveScroll: true });
}

async function withdraw() {
    const confirmed = await confirm(
        props.listing.is_winner
            ? {
                  title: 'Từ bỏ kết quả đấu giá này?',
                  description:
                      'Người đặt giá cao kế tiếp sẽ trở thành người thắng và bạn không thể lấy lại. Từ bỏ nhiều lần sẽ bị tạm khoá quyền đặt giá.',
                  confirmLabel: 'Từ bỏ kết quả',
                  destructive: true,
              }
            : {
                  title: 'Xác nhận người thắng không mua?',
                  description:
                      'Người đặt giá cao kế tiếp sẽ trở thành người thắng; nếu không còn ai, phiên sẽ bị huỷ. Không hoàn tác được.',
                  confirmLabel: 'Xác nhận không mua',
                  destructive: true,
              },
    );

    if (confirmed) {
        router.post(
            withdrawWinner(props.listing.id).url,
            {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <section
        class="space-y-4 rounded-lg border border-green-600/40 bg-green-600/5 p-4"
    >
        <div>
            <h2 class="text-lg font-semibold">
                {{
                    listing.is_winner
                        ? 'Bạn đã thắng phiên này'
                        : 'Phiên của bạn đã có người thắng'
                }}
            </h2>
            <p class="text-sm text-muted-foreground">
                Giá chốt {{ formatVnd(listing.winning_price) }}. Hãy liên hệ
                {{ listing.is_winner ? 'người bán' : 'người thắng' }} để hoàn
                tất giao dịch.
            </p>
        </div>

        <dl v-if="listing.contact" class="grid gap-2 text-sm sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-muted-foreground">
                    {{ listing.is_winner ? 'Người bán' : 'Người thắng' }}
                </dt>
                <dd class="font-medium">{{ listing.contact.name }}</dd>
            </div>
            <div class="flex items-center gap-2">
                <Mail class="size-4 shrink-0 text-muted-foreground" />
                <a
                    :href="`mailto:${listing.contact.email}`"
                    class="truncate underline underline-offset-4"
                    >{{ listing.contact.email }}</a
                >
            </div>
            <div class="flex items-center gap-2">
                <Phone class="size-4 shrink-0 text-muted-foreground" />
                <a
                    v-if="listing.contact.phone"
                    :href="`tel:${listing.contact.phone}`"
                    class="underline underline-offset-4"
                    >{{ listing.contact.phone }}</a
                >
                <span v-else class="text-muted-foreground"
                    >Chưa cung cấp số điện thoại</span
                >
            </div>
            <div
                v-if="listing.contact.address"
                class="flex items-center gap-2 sm:col-span-2"
            >
                <MapPin class="size-4 shrink-0 text-muted-foreground" />
                {{ listing.contact.address }}
            </div>
            <div
                v-if="listing.contact.shipping"
                class="rounded-md border bg-muted/40 p-3 sm:col-span-2"
            >
                <dt class="flex items-center gap-2 text-muted-foreground">
                    <Truck class="size-4 shrink-0" />
                    Địa chỉ nhận hàng
                </dt>
                <dd class="mt-1 space-y-0.5">
                    <p class="font-medium">
                        {{ listing.contact.shipping.name }} ·
                        <a
                            :href="`tel:${listing.contact.shipping.phone}`"
                            class="underline underline-offset-4"
                            >{{ listing.contact.shipping.phone }}</a
                        >
                    </p>
                    <p>{{ listing.contact.shipping.address }}</p>
                </dd>
            </div>
            <p
                v-else-if="!listing.is_winner"
                class="text-muted-foreground sm:col-span-2"
            >
                Người thắng chưa lưu địa chỉ nhận hàng. Hãy hỏi trực tiếp khi
                liên hệ.
            </p>
        </dl>

        <form class="space-y-2 border-t pt-4" @submit.prevent="submitReview">
            <Label>
                Đánh giá
                {{ listing.is_winner ? 'người bán' : 'người thắng' }}
            </Label>
            <div><StarRating v-model="form.rating" /></div>
            <InputError :message="form.errors.rating" />
            <textarea
                v-model="form.comment"
                rows="2"
                maxlength="1000"
                placeholder="Nhận xét của bạn (tuỳ chọn)"
                aria-label="Nhận xét"
                class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError :message="form.errors.comment" />
            <Button
                type="submit"
                size="sm"
                :disabled="form.processing || form.rating < 1"
            >
                {{ listing.my_review ? 'Cập nhật đánh giá' : 'Gửi đánh giá' }}
            </Button>
        </form>

        <div class="border-t pt-4">
            <Button variant="outline" size="sm" @click="withdraw">
                {{
                    listing.is_winner
                        ? 'Tôi không mua nữa'
                        : 'Người thắng không mua'
                }}
            </Button>
            <p class="mt-1 text-xs text-muted-foreground">
                Kết quả sẽ chuyển cho người đặt giá cao kế tiếp.
            </p>
        </div>
    </section>
</template>
