/**
 * Rupiah helpers. IDR is treated as a whole-number currency — no decimals.
 */

/** Strip everything except digits, returning a bare digit string ('' when empty). */
export function parseDigits(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).replace(/\D/g, '');
}

/** Group digits with id-ID thousand separators, e.g. 1500000 -> "1.500.000". */
export function formatThousands(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '';
    }

    return Math.trunc(value).toLocaleString('id-ID');
}

/** Full display form, e.g. 1500000 -> "Rp1.500.000". */
export function formatRupiah(value: number | null | undefined): string {
    return `Rp${formatThousands(Number(value ?? 0))}`;
}
