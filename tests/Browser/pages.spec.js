import { expect, test } from '@playwright/test';
import { signIn } from './helpers.js';

/** Every authenticated page, loaded for real, with the console watched. */
const PAGES = [
    '/dashboard', '/performance', '/strength-levels', '/muscle', '/body',
    '/photos', '/nutrition', '/projections', '/routines', '/goals',
    '/ai', '/guide', '/write-operations', '/profile', '/import',
];

test('every page loads without a JS error', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(`${page.url()}: ${e.message}`));
    page.on('console', (m) => m.type() === 'error' && errors.push(`${page.url()}: ${m.text()}`));

    await signIn(page);

    for (const path of PAGES) {
        const response = await page.goto(path, { waitUntil: 'networkidle' });
        expect(response.status(), `${path} should return 200`).toBe(200);
    }

    expect(errors).toEqual([]);
});

/**
 * The landing page, checked signed-out because `/` redirects to the dashboard
 * for anyone signed in — so the suite above never sees it.
 */
test('the landing page loads clean for a visitor', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(`${page.url()}: ${e.message}`));
    page.on('console', (m) => m.type() === 'error' && errors.push(`${page.url()}: ${m.text()}`));

    const response = await page.goto('/', { waitUntil: 'networkidle' });

    expect(response.status()).toBe(200);
    expect(errors).toEqual([]);
});

test('the page body never scrolls sideways', async ({ page }) => {
    const overflowOf = () => page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );

    // The landing page is the widest layout in the app and the only one a
    // visitor sees on a phone before deciding anything, so it is checked at a
    // narrow viewport too.
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/', { waitUntil: 'networkidle' });
        const overflow = await overflowOf();
        expect(overflow, `/ at ${width}px scrolls horizontally by ${overflow}px`).toBeLessThanOrEqual(0);
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await signIn(page);

    // Tooltips on edge cards used to push the document wider than the viewport.
    for (const path of ['/dashboard', '/body', '/performance']) {
        await page.goto(path, { waitUntil: 'networkidle' });
        const overflow = await overflowOf();
        expect(overflow, `${path} scrolls horizontally by ${overflow}px`).toBeLessThanOrEqual(0);
    }
});
