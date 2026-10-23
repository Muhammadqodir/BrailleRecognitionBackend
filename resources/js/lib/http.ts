/** JSON requests from dashboard pages outside Inertia's own forms, with Laravel's CSRF cookie. */

function xsrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

/** The session ran out (401) or its CSRF token with it (419): signing in again fixes it. */
export class SignedOut extends Error {}

export async function send<T>(url: string, method: string, body: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf() },
        body: JSON.stringify(body),
    });
    if (response.status === 401 || response.status === 419) throw new SignedOut();
    const json = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(json.message ?? `Request failed with ${response.status}`);
    return json as T;
}
