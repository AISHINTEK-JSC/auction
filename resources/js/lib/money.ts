// Helpers for MoneyInput: whole đồng amounts shown with thousands separators ("1.500.000").

/** Largest amount the server accepts (StoreListingRequest / PlaceBidRequest). */
const MAX_DIGITS = 12;

const grouping = new Intl.NumberFormat('vi-VN');

export function parseMoney(text: string): number | '' {
    const digits = text
        .replace(/\D/g, '')
        .replace(/^0+(?=\d)/, '')
        .slice(0, MAX_DIGITS);

    return digits === '' ? '' : Number(digits);
}

export function formatMoney(value: number | '' | null | undefined): string {
    return value === '' || value === null || value === undefined
        ? ''
        : grouping.format(value);
}

/**
 * Where the caret belongs in `formatted` so that the same number of digits
 * stays to its right as before the text was reformatted.
 */
export function caretPosition(
    formatted: string,
    digitsAfterCaret: number,
): number {
    let position = formatted.length;
    let seen = 0;

    while (position > 0 && seen < digitsAfterCaret) {
        position--;

        if (/\d/.test(formatted[position])) {
            seen++;
        }
    }

    return position;
}
