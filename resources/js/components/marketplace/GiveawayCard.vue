<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Gift, Users } from '@lucide/vue';
import Countdown from '@/components/marketplace/Countdown.vue';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/giveaways';
import type { Giveaway } from '@/types';

defineProps<{
    giveaway: Giveaway;
}>();
</script>

<template>
    <Link
        :href="show(giveaway.id)"
        class="group flex flex-col overflow-hidden rounded-lg border bg-card text-card-foreground transition-shadow hover:shadow-md"
    >
        <div class="relative aspect-video bg-muted">
            <img
                v-if="giveaway.image"
                :src="giveaway.image"
                :alt="giveaway.title"
                loading="lazy"
                class="size-full object-cover"
            />
            <div
                v-else
                class="flex size-full items-center justify-center text-muted-foreground"
            >
                <Gift class="size-10" />
            </div>
            <Badge
                class="absolute top-2 left-2"
                :variant="giveaway.open ? 'default' : 'secondary'"
            >
                {{
                    giveaway.open
                        ? 'Đang nhận đăng ký'
                        : giveaway.drawn_at
                          ? 'Đã quay thưởng'
                          : 'Chờ quay thưởng'
                }}
            </Badge>
            <Badge
                v-if="giveaway.won"
                class="absolute top-2 right-2 bg-amber-500 text-white"
            >
                Bạn đã trúng
            </Badge>
            <Badge
                v-else-if="giveaway.joined"
                variant="secondary"
                class="absolute top-2 right-2"
            >
                Đã đăng ký
            </Badge>
        </div>
        <div class="flex flex-1 flex-col gap-1 p-3">
            <h3 class="line-clamp-2 font-medium group-hover:underline">
                {{ giveaway.title }}
            </h3>
            <p class="line-clamp-1 text-sm text-muted-foreground">
                Quà: {{ giveaway.prize }}
            </p>
            <p
                class="mt-auto flex items-center justify-between gap-2 pt-2 text-xs text-muted-foreground"
            >
                <span class="flex items-center gap-1">
                    <Users class="size-3.5" />
                    {{ giveaway.entry_count }} người tham gia
                </span>
                <span v-if="giveaway.open">
                    Còn
                    <Countdown
                        :deadline="giveaway.ends_at"
                        done-label="hết hạn"
                        class="font-medium text-foreground"
                    />
                </span>
            </p>
        </div>
    </Link>
</template>
