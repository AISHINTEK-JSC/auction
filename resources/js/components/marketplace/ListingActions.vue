<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Bell, BellRing, Flag, Pencil } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useAuthUser } from '@/composables/useAuthUser';
import { useWatchListing } from '@/composables/useWatchListing';
import { edit } from '@/routes/listings';
import { store as report } from '@/routes/listings/reports';
import type { ListingDetail } from '@/types';

const props = defineProps<{
    listing: ListingDetail;
    reportReasons: { value: string; label: string }[];
}>();

const user = useAuthUser();
const reporting = ref(false);

const form = useForm({
    reason: props.reportReasons[0]?.value ?? '',
    note: '',
});

const toggleWatch = useWatchListing(() => props.listing.id);

function submitReport() {
    form.post(report(props.listing.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            reporting.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            variant="outline"
            size="sm"
            :aria-pressed="listing.watching"
            @click="toggleWatch"
        >
            <BellRing v-if="listing.watching" class="size-4 text-amber-500" />
            <Bell v-else class="size-4" />
            {{ listing.watching ? 'Đang theo dõi' : 'Theo dõi' }}
            <span class="text-muted-foreground"
                >({{ listing.watcher_count }})</span
            >
        </Button>

        <Button v-if="listing.can.edit" variant="outline" size="sm" as-child>
            <Link :href="edit(listing.id)">
                <Pencil class="size-4" />
                Sửa tin
            </Link>
        </Button>

        <Dialog v-if="user && !listing.is_owner" v-model:open="reporting">
            <DialogTrigger as-child>
                <Button variant="ghost" size="sm" class="text-muted-foreground">
                    <Flag class="size-4" />
                    Báo cáo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form class="space-y-4" @submit.prevent="submitReport">
                    <DialogHeader>
                        <DialogTitle>Báo cáo tin vi phạm</DialogTitle>
                        <DialogDescription>
                            Kiểm duyệt viên sẽ xem xét tin này. Người bán không
                            biết ai đã báo cáo.
                        </DialogDescription>
                    </DialogHeader>

                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">Lý do</legend>
                        <label
                            v-for="reason in reportReasons"
                            :key="reason.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="form.reason"
                                type="radio"
                                name="reason"
                                :value="reason.value"
                            />
                            {{ reason.label }}
                        </label>
                        <InputError :message="form.errors.reason" />
                    </fieldset>

                    <div class="grid gap-2">
                        <Label for="report-note">Chi tiết (tuỳ chọn)</Label>
                        <textarea
                            id="report-note"
                            v-model="form.note"
                            rows="3"
                            maxlength="1000"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError :message="form.errors.note" />
                    </div>

                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">
                            Gửi báo cáo
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
