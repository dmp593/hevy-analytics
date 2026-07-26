import { expect, test } from '@playwright/test';

/** Every authenticated page, loaded for real, with the console watched. */
const PAGES = [
    '/dashboard', '/performance', '/strength-levels', '/muscle', '/body',
    '/photos', '/nutrition', '/projections', '/routines', '/goals',
    '/ai', '/guide', '/write-operations', '/profile',
];

test('every page loads without a JS error', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(`${page.url()}: ${e.message}`));
    page.on('console', (m) => m.type() === 'error' && errors.push(`${page.url()}: ${m.text()}`));

    await page.goto('/login');
    await page.fill('input[name=email]', 'demo@example.test');
    await page.fill('input[name=password]', 'password');
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard');

    for (const path of PAGES) {
        const response = await page.goto(path, { waitUntil: 'networkidle' });
        expect(response.status(), `${path} should return 200`).toBe(200);
    }

    expect(errors).toEqual([]);
});

test('the page body never scrolls sideways', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name=email]', 'demo@example.test');
    await page.fill('input[name=password]', 'password');
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard');

    // Tooltips on edge cards used to push the document wider than the viewport.
    for (const path of ['/dashboard', '/body', '/performance']) {
        await page.goto(path, { waitUntil: 'networkidle' });
        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        );
        expect(overflow, `${path} scrolls horizontally by ${overflow}px`).toBeLessThanOrEqual(0);
    }
});
