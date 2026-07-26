import { expect, test } from '@playwright/test';

const EMAIL = 'demo@example.test';
const PASSWORD = 'password';

/** Pages that must render at least one chart against the demo account. */
const CHART_PAGES = [
    { path: '/dashboard', charts: 2 },
    { path: '/body', charts: 4 },
    { path: '/muscle', charts: 1 },
    { path: '/nutrition', charts: 1 },
    { path: '/performance', charts: 1 },
];

async function signIn(page) {
    await page.goto('/login');
    await page.fill('input[name=email]', EMAIL);
    await page.fill('input[name=password]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard');
}

/**
 * Read the canvases directly: a chart that failed to initialise still leaves a
 * <canvas> in the DOM, so counting elements proves nothing. Non-transparent
 * pixels are the only honest signal that something was drawn.
 */
async function inspectCharts(page) {
    return page.evaluate(() => {
        const instances = window.Chart ? Object.values(window.Chart.instances) : [];

        return {
            chartGlobal: typeof window.Chart,
            canvases: document.querySelectorAll('canvas').length,
            live: instances.length,
            detached: instances.filter((c) => c.canvas && !c.canvas.isConnected).length,
            painted: [...document.querySelectorAll('canvas')].filter((c) => {
                if (!c.width) return false;
                const data = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
                for (let i = 3; i < data.length; i += 4) {
                    if (data[i] !== 0) return true;
                }
                return false;
            }).length,
        };
    });
}

test.describe('charts', () => {
    for (const { path, charts } of CHART_PAGES) {
        test(`${path} paints ${charts} chart(s) with no JS errors`, async ({ page }) => {
            const errors = [];
            page.on('pageerror', (e) => errors.push(e.message));
            page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));

            await signIn(page);
            await page.goto(path, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);

            const result = await inspectCharts(page);

            // Chart.js was loaded from a CDN and failed silently when the request
            // did not land.
            expect(result.chartGlobal, 'Chart.js must be bundled, not fetched').toBe('function');
            expect(result.canvases).toBe(charts);
            expect(result.painted, 'every canvas must have pixels drawn on it').toBe(charts);
            expect(errors).toEqual([]);
        });
    }

    test('filtering repeatedly does not blank the chart or leak instances', async ({ page }) => {
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));

        await signIn(page);
        await page.goto('/performance', { waitUntil: 'networkidle' });
        await page.waitForTimeout(800);

        const periods = await page.$$eval(
            'form[x-target] select[name=period] option',
            (options) => options.map((o) => o.value),
        );

        // The original bug: the second filter apply left the canvas permanently
        // blank, and every apply stranded another Chart on the same canvas.
        for (let i = 1; i <= 5; i++) {
            await page.selectOption('form[x-target] select[name=period]', periods[i % periods.length]);
            await page.click('form[x-target] button');
            await page.waitForTimeout(900);

            const result = await inspectCharts(page);
            expect(result.painted, `chart went blank after apply #${i}`).toBe(1);
            expect(result.live, `leaked a Chart instance on apply #${i}`).toBe(1);
            expect(result.detached, `stranded a chart on a detached canvas on apply #${i}`).toBe(0);
        }

        expect(errors).toEqual([]);
    });
});
