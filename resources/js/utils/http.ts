/**
 * Minimal JSON POST for the quick-create flows (category / customer) that the
 * backend serves as JSON when the request is XHR. Uses `fetch` plus Laravel's
 * XSRF cookie, so no extra HTTP client is pulled in.
 */

function readCookie(name: string): string {
    const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));

    return match ? decodeURIComponent(match[1]) : '';
}

export interface JsonResponse<T> {
    ok: boolean;
    status: number;
    data: T;
}

export async function postJson<T = unknown>(
    url: string,
    body: Record<string, unknown>,
): Promise<JsonResponse<T>> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
        },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });

    let data: T;
    try {
        data = (await response.json()) as T;
    } catch {
        data = {} as T;
    }

    return { ok: response.ok, status: response.status, data };
}
