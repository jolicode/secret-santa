/**
 * POST to one of our AJAX endpoints and decode the JSON response.
 */
export function postJson(url, body = undefined) {
    return fetch(url, {
        method: 'post',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body,
    }).then((response) => response.json());
}
