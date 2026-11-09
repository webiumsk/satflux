import { expect, test } from '@playwright/test';

for (const viewport of [{ width: 1280, height: 800 }, { width: 768, height: 600 }, { width: 767, height: 900 }, { width: 390, height: 844 }]) {
    test(`store footer stays inside its scroll owner at ${viewport.width}px`, async ({ page }) => {
        await page.setViewportSize(viewport);
        await page.route('**/api/stores', route => route.fulfill({ json: { data: Array.from({ length: 30 }, (_, index) => ({
            id: `sample-${index}`, name: `Layout sample ${index}`, wallet_type: null,
            created_at: '2026-01-01T00:00:00Z', updated_at: '2026-01-01T00:00:00Z',
        })) } }));
        await page.goto('/stores');
        await expect(page.getByRole('heading', { name: 'Layout sample 29', exact: true })).toBeAttached();

        // A clipped sr-only label must not escape to the document's containing block.
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollHeight)).toBe(viewport.height);
        const owners = await page.evaluate(() => [...document.querySelectorAll<HTMLElement>('#app *')]
            .filter(element => ['auto', 'scroll'].includes(getComputedStyle(element).overflowY)
                && element.scrollHeight > element.clientHeight + 1)
            .map(element => {
                element.scrollTop = element.scrollHeight;
                return { height: element.clientHeight, bottom: element.getBoundingClientRect().bottom };
            }));
        expect(owners).toHaveLength(1);
        expect(owners[0].height).toBeGreaterThan(0);
        expect(owners[0].bottom).toBeLessThanOrEqual(viewport.height);

        if (viewport.width >= 768) {
            const footer = page.locator('footer');
            await expect(footer).toBeVisible();
            const box = (await footer.boundingBox())!;
            expect(box.y + box.height).toBeCloseTo(owners[0].bottom, 0);
        } else {
            await expect(page.locator('footer')).toBeHidden();
        }
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        expect(await page.evaluate(() => window.scrollY)).toBe(0);
    });
}
