import { readFileSync } from 'node:fs';
import { afterEach, expect, it, vi } from 'vitest';

const widget = readFileSync('resources/vendor/chorala/widget.js', 'utf8');

afterEach(() => {
    window.Chorala?.('close');
    document.body.innerHTML = '';
    delete window.Chorala;
    vi.unstubAllGlobals();
});

it('the pinned SDK preserves a failed-submit draft across JWT refresh and uses the new token on retry', async () => {
    const requests: Array<{ url: string; options: RequestInit }> = [];
    let submissions = 0;
    vi.stubGlobal('fetch', vi.fn(async (url: string, options: RequestInit) => {
        requests.push({ url, options });
        if (url.startsWith('https://feedback.example.test/api/v1/public/boards')) {
            return Response.json({ boards: [{ slug: 'bugs', name: 'Bugs', kind: 'bug' }], posts: [] });
        }
        if (url === 'https://feedback.example.test/api/v1/public/posts' && options.method === 'POST') {
            submissions += 1;
            return submissions === 1
                ? Response.json({ error: { message: 'Expired synthetic identity' } }, { status: 401 })
                : Response.json({ id: 'synthetic-post' });
        }
        throw new Error('Unexpected widget request.');
    }));
    const queue = [['init', {
        projectKey: 'pk_synthetic',
        apiUrl: 'https://feedback.example.test/api/v1',
        user: { jwt: 'initial-synthetic-token' },
        settings: { mode: 'manual' },
        locale: 'en',
    }]];
    window.Chorala = Object.assign(() => undefined, { q: queue });
    window.eval(widget);
    expect(window.Chorala.supportsIdentityRefresh).toBe(true);
    const host = document.querySelector('[data-chorala-widget]');
    expect(host).not.toBeNull();
    await vi.waitFor(() => {
        window.Chorala?.('open');
        expect(host!.shadowRoot?.querySelector('.chorala-panel')).not.toBeNull();
    });
    await vi.waitFor(() => expect(host!.shadowRoot?.textContent).toContain('Submit an idea'));
    const newPost = [...host!.shadowRoot!.querySelectorAll('button')].find(button => button.textContent?.includes('Submit an idea'))!;
    newPost.click();
    await vi.waitFor(() => expect(host!.shadowRoot?.querySelector('form')).not.toBeNull());
    const form = host!.shadowRoot!.querySelector('form')!;
    const title = form.querySelector('input')!;
    const details = form.querySelector('textarea')!;
    title.value = 'Synthetic draft title';
    title.dispatchEvent(new Event('input', { bubbles: true }));
    details.value = 'Synthetic draft details';
    details.dispatchEvent(new Event('input', { bubbles: true }));
    await Promise.resolve();
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await vi.waitFor(() => expect(form.textContent).toContain('Expired synthetic identity'));
    window.Chorala?.('refreshIdentity', { jwt: 'refreshed-synthetic-token' });
    expect(document.querySelector('[data-chorala-widget]')).toBe(host);
    expect(host!.shadowRoot!.querySelector('form')).toBe(form);
    expect(title.value).toBe('Synthetic draft title');
    expect(details.value).toBe('Synthetic draft details');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await vi.waitFor(() => expect(submissions).toBe(2));
    const posts = requests.filter(request => request.options.method === 'POST');
    expect(posts[0].options.headers).toEqual(expect.objectContaining({ 'x-chorala-user': 'initial-synthetic-token' }));
    expect(posts[1].options.headers).toEqual(expect.objectContaining({ 'x-chorala-user': 'refreshed-synthetic-token' }));
    expect(posts[1].options.headers).not.toHaveProperty('X-XSRF-TOKEN');
    window.Chorala?.('identify', {});
    expect(document.querySelector('[data-chorala-widget]')).not.toBe(host);
    expect(document.querySelector('[data-chorala-widget]')!.shadowRoot!.querySelector('form')).toBeNull();
});
