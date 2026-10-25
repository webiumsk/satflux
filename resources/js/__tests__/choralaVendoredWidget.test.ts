import { readFileSync } from 'node:fs';
import { afterEach, expect, it, vi } from 'vitest';

const widget = readFileSync('resources/vendor/chorala/widget.js', 'utf8');
const originalUrl = window.location.href;

afterEach(() => {
    window.Chorala?.('close');
    document.body.innerHTML = '';
    delete window.Chorala;
    window.history.replaceState(null, '', originalUrl);
    vi.restoreAllMocks();
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


async function openSyntheticDraft(locale = 'en', submitLabel = 'Submit an idea'): Promise<HTMLFormElement> {
    window.Chorala = Object.assign(() => undefined, {
        q: [['init', {
            projectKey: 'pk_synthetic_owned_widget',
            apiUrl: 'https://feedback.example.test/api/v1',
            appVersion: 'synthetic-app-version',
            settings: { mode: 'manual' },
            locale,
        }]],
    });
    window.eval(widget);
    await vi.waitFor(() => {
        window.Chorala?.('open');
        expect(document.querySelector('[data-chorala-widget]')?.shadowRoot?.querySelector('.chorala-panel')).not.toBeNull();
    });
    const shadow = document.querySelector('[data-chorala-widget]')!.shadowRoot!;
    await vi.waitFor(() => expect(shadow.textContent).toContain(submitLabel));
    [...shadow.querySelectorAll('button')].find(button => button.textContent?.includes(submitLabel))!.click();
    await vi.waitFor(() => expect(shadow.querySelector('form')).not.toBeNull());
    return shadow.querySelector('form')!;
}

function fillSyntheticDraft(form: HTMLFormElement): void {
    const title = form.querySelector('input')!;
    title.value = 'Synthetic private-context regression';
    title.dispatchEvent(new Event('input', { bubbles: true }));
    const body = form.querySelector('textarea')!;
    body.value = 'User-entered feedback remains available.';
    body.dispatchEvent(new Event('input', { bubbles: true }));
}

it.each([
    ['https://reader:synthetic-password@referrer.example.test/private/path?private_key=synthetic#fragment', 'https://referrer.example.test'],
    ['javascript:synthetic-private-context', undefined],
    ['', undefined],
])('the actual pinned SDK limits submitted URL/referrer context to HTTP origins (%s)', async (referrer, expectedReferrer) => {
    window.history.replaceState(null, '', '/merchant/private-store/recovery?synthetic_token=private#secret-fragment');
    vi.spyOn(document, 'referrer', 'get').mockReturnValue(referrer);
    const submissions: Array<{ metadata: Record<string, string>; title: string; body: string; appVersion: string }> = [];
    const requests: string[] = [];
    vi.stubGlobal('fetch', vi.fn(async (url: string, options: RequestInit) => {
        requests.push(url);
        if (url.startsWith('https://feedback.example.test/api/v1/public/boards')) {
            return Response.json({ boards: [{ slug: 'ideas', name: 'Ideas', kind: 'feature' }], posts: [] });
        }
        if (url === 'https://feedback.example.test/api/v1/public/posts' && options.method === 'POST') {
            submissions.push(JSON.parse(String(options.body)));
            return Response.json({ id: 'synthetic-origin-only-post' });
        }
        throw new Error('Unexpected widget request.');
    }));
    const form = await openSyntheticDraft();
    fillSyntheticDraft(form);
    await Promise.resolve();
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await vi.waitFor(() => expect(submissions).toHaveLength(1));
    expect(submissions[0]).toEqual(expect.objectContaining({
        title: 'Synthetic private-context regression',
        body: 'User-entered feedback remains available.',
        appVersion: 'synthetic-app-version',
    }));
    expect(submissions[0].metadata.url).toBe(window.location.origin);
    expect(submissions[0].metadata.referrer).toBe(expectedReferrer);
    expect(JSON.stringify(submissions[0].metadata)).not.toMatch(/private-store|synthetic_token|secret-fragment|reader|synthetic-password|private_key|private\/path/);
    expect(requests.every(url => url.startsWith('https://feedback.example.test/api/v1/public/boards')
        || url === 'https://feedback.example.test/api/v1/public/posts')).toBe(true);
});

it.each([
    ['en', 'Submit an idea', 'Title'],
    ['sk', 'Odoslať návrh', 'Názov'],
    ['es', 'Enviar una idea', 'Título'],
    ['de', 'Vorschlag einreichen', 'Titel'],
    ['cs', 'Odeslat návrh', 'Název'],
])('the actual pinned SDK renders the Satflux %s locale', async (locale, submitLabel, titleLabel) => {
    const requests: string[] = [];
    vi.stubGlobal('fetch', vi.fn(async (url: string) => {
        requests.push(url);
        if (url.startsWith('https://feedback.example.test/api/v1/public/boards')) {
            return Response.json({ boards: [{ slug: 'ideas', name: 'Ideas', kind: 'feature' }], posts: [] });
        }
        throw new Error('Unexpected widget request.');
    }));
    const form = await openSyntheticDraft(locale, submitLabel);
    expect(form.textContent).toContain(titleLabel);
    expect(requests.some(url => new URL(url).searchParams.get('locale') === locale)).toBe(true);
});
