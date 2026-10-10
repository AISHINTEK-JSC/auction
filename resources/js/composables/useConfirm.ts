import { reactive } from 'vue';

export type ConfirmOptions = {
    /** The question, e.g. "Xoá bình luận này?". */
    title: string;
    /** What happens if the person goes ahead. */
    description: string;
    /** Names the action itself ("Xoá", "Gỡ tin") rather than "OK". */
    confirmLabel: string;
    cancelLabel?: string;
    /** Red confirm button, for something that cannot be undone. */
    destructive?: boolean;
};

type ConfirmState = Required<ConfirmOptions> & { open: boolean };

/**
 * What the one ConfirmDialog on the page shows. It is mounted by the layout;
 * everything else only calls confirm().
 */
export const confirmState = reactive<ConfirmState>({
    open: false,
    title: '',
    description: '',
    confirmLabel: '',
    // Not "Huỷ": several of the things being confirmed are themselves a "huỷ".
    cancelLabel: 'Quay lại',
    destructive: false,
});

let settle: ((confirmed: boolean) => void) | null = null;

/**
 * Ask before doing something that is hard to take back. Resolves to true when
 * the person confirms, false when they cancel, press Esc or another question
 * replaces this one.
 *
 *     if (await confirm({ title: 'Gỡ tin?', description: '…', confirmLabel: 'Gỡ tin' })) { … }
 */
export function confirm(options: ConfirmOptions): Promise<boolean> {
    settle?.(false);

    Object.assign(confirmState, {
        cancelLabel: 'Quay lại',
        destructive: false,
        ...options,
        open: true,
    });

    return new Promise((resolve) => {
        settle = resolve;
    });
}

/** Called by ConfirmDialog with the person's answer. */
export function answerConfirm(confirmed: boolean) {
    confirmState.open = false;
    settle?.(confirmed);
    settle = null;
}
