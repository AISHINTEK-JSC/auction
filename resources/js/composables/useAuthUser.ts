import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { User } from '@/types';

/**
 * The signed-in user, or null for guests. The marketplace pages are public, so
 * unlike the starter kit's settings pages they cannot assume a user.
 */
export function useAuthUser() {
    const page = usePage();

    return computed(() => (page.props.auth.user as User | null) ?? null);
}
