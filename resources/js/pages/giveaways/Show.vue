<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Gift, Trophy, Users } from '@lucide/vue';
import Countdown from '@/components/marketplace/Countdown.vue';
import { Button } from '@/components/ui/button';
import { useAuthUser } from '@/composables/useAuthUser';
import { formatDateTime } from '@/lib/format';
import { login } from '@/routes';
import { index, join } from '@/routes/giveaways';
import type { Giveaway } from '@/types';

const props = defineProps<{
    giveaway: Giveaway & {
        description: string | null;
        winners: { name: string; member_code: string | null }[];
    };
}>();

const user = useAuthUser();

function enter() {
    router.post(join(props.giveaway.id).url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="giveaway.title" />

    <div class="mx-auto max-w-3xl space-y-6">
        <Link
            :href="index()"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Tất cả quà tặng
        </Link>

        <div class="overflow-hidden rounded-lg border bg-background">
            <div
                class="flex aspect-video items-center justify-center bg-muted text-muted-foreground"
            >
                <img
                    v-if="giveaway.image"
                    :src="giveaway.image"
                    :alt="giveaway.title"
                    class="size-full object-cover"
                />
                <Gift v-else class="size-16" />
            </div>

            <div class="space-y-4 p-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ giveaway.title }}</h1>
                    <p class="mt-1 text-lg">
                        Quà tặng:
                        <span class="font-semibold">{{ giveaway.prize }}</span>
                    </p>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-muted-foreground">Số người trúng</dt>
                        <dd class="text-lg font-semibold">
                            {{ giveaway.winner_count }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Đã đăng ký</dt>
                        <dd
                            class="flex items-center gap-1 text-lg font-semibold"
                        >
                            <Users class="size-4" />
                            {{ giveaway.entry_count }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            {{ giveaway.open ? 'Còn lại' : 'Hạn đăng ký' }}
                        </dt>
                        <dd class="text-lg font-semibold">
                            <Countdown
                                v-if="giveaway.open"
                                :deadline="giveaway.ends_at"
                                done-label="Đã hết hạn"
                            />
                            <template v-else>{{
                                formatDateTime(giveaway.ends_at)
                            }}</template>
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="giveaway.description"
                    class="text-sm leading-6 break-words whitespace-pre-line"
                >
                    {{ giveaway.description }}
                </p>

                <!-- Entry state -->
                <div class="rounded-lg border bg-muted/40 p-4">
                    <template v-if="giveaway.won">
                        <p
                            class="flex items-center gap-2 text-lg font-semibold"
                        >
                            <Trophy class="size-5 text-amber-500" />
                            Chúc mừng, bạn đã trúng thưởng!
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Mã số của bạn:
                            <span
                                class="font-mono font-semibold text-foreground"
                                >{{ user?.member_code }}</span
                            >. Chúng tôi sẽ liên hệ qua email để trao quà.
                        </p>
                    </template>
                    <template v-else-if="giveaway.joined">
                        <p class="font-medium">Bạn đã đăng ký tham gia.</p>
                        <p class="text-sm text-muted-foreground">
                            Mã số của bạn:
                            <span
                                class="font-mono text-base font-semibold text-foreground"
                                >{{ user?.member_code }}</span
                            >
                            <template v-if="giveaway.drawn_at">
                                . Rất tiếc, lần này bạn chưa trúng.
                            </template>
                            <template v-else>
                                . Kết quả sẽ được thông báo sau khi quay thưởng.
                            </template>
                        </p>
                    </template>
                    <template v-else-if="!giveaway.open">
                        <p class="text-sm text-muted-foreground">
                            Chương trình đã đóng đăng ký.
                        </p>
                    </template>
                    <template v-else-if="user">
                        <p class="mb-3 text-sm text-muted-foreground">
                            Bạn sẽ tham gia bằng mã số thành viên
                            <span
                                class="font-mono font-semibold text-foreground"
                                >{{ user.member_code }}</span
                            >. Mã số này là của riêng bạn và không thay đổi.
                        </p>
                        <Button size="lg" @click="enter">
                            <Gift class="size-4" />
                            Đăng ký tham gia
                        </Button>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="font-medium underline underline-offset-4"
                            >Đăng nhập</Link
                        >
                        để đăng ký tham gia.
                    </template>
                </div>

                <section v-if="giveaway.drawn_at" class="space-y-2">
                    <h2 class="text-lg font-semibold">
                        Người trúng thưởng
                        <span class="text-sm font-normal text-muted-foreground">
                            (quay lúc {{ formatDateTime(giveaway.drawn_at) }})
                        </span>
                    </h2>
                    <ul class="divide-y rounded-lg border">
                        <li
                            v-for="winner in giveaway.winners"
                            :key="winner.member_code ?? winner.name"
                            class="flex items-center justify-between gap-3 px-4 py-2 text-sm"
                        >
                            <span class="flex items-center gap-2 font-medium">
                                <Trophy class="size-4 text-amber-500" />
                                {{ winner.name }}
                            </span>
                            <span class="font-mono">{{
                                winner.member_code
                            }}</span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>
