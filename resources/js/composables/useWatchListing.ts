import { router } from '@inertiajs/vue3';
import { useAuthUser } from '@/composables/useAuthUser';
import { login } from '@/routes';
import { watch } from '@/routes/listings';

/**
 * Follow or unfollow a listing. Guests are sent to sign in first.
 */
export function useWatchListing(listingId: () => number) {
    const user = useAuthUser();

    return function toggleWatch() {
        if (!user.value) {
            router.visit(login());

            return;
        }

        router.post(
            watch(listingId()).url,
            {},
            { preserveScroll: true, only: ['listing'] },
        );
    };
}
