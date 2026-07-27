import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

/**
 * Theme and contrast guard.
 *
 * A dark mode is easy to ship half-finished: the shell flips, and a form
 * control, a chart track or a plugin's hardcoded colour stays light. Nothing in
 * a PHP test can see that, and it is invisible to anyone developing in light
 * mode. This suite walks every page in both themes and fails on:
 *
 *   1. an opaque near-white background while dark mode is on
 *   2. any text below its WCAG AA contrast minimum
 *
 * Both checks found real bugs on first run — white <select>s in dark mode,
 * white-on-brand buttons at 2.98:1, and the whole guide page's prose at 1.01:1.
 */

const PAGES = [
    '/dashboard', '/body', '/muscle', '/nutrition', '/performance', '/routines',
    '/strength-levels', '/projections', '/goals', '/photos', '/guide', '/ai',
    '/write-operations', '/profile', '/import',
];

/**
 * Runs in the page. Returns every element that breaks one of the two rules.
 *
 * Colours are normalised by painting them onto a 1x1 canvas and reading the
 * pixel back, because getComputedStyle reports oklch() and color-mix() verbatim
 * in Chromium — a regex expecting "rgb(...)" silently parses none of this app's
 * colours and reports everything as passing.
 */
const audit = () => {
    const cv = document.createElement('canvas');
    cv.width = cv.height = 1;
    const cx = cv.getContext('2d', { willReadFrequently: true });
    const cache = new Map();

    const parse = (s) => {
        if (!s) return null;
        if (cache.has(s)) return cache.get(s);
        cx.clearRect(0, 0, 1, 1);
        cx.fillStyle = '#000';
        cx.fillStyle = s;
        // An unparseable value leaves fillStyle untouched; a genuine black
        // round-trips to #000000 too, so distinguish the two explicitly.
        if (cx.fillStyle === '#000000' && !/^(#000|black|rgba?\(0,\s*0,\s*0)/.test(s.trim())) {
            cache.set(s, null);
            return null;
        }
        cx.fillRect(0, 0, 1, 1);
        const [r, g, b, a] = cx.getImageData(0, 0, 1, 1).data;
        const out = { r, g, b, a: a / 255 };
        cache.set(s, out);
        return out;
    };

    const lum = ({ r, g, b }) => {
        const f = (c) => {
            c /= 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
    };
    const over = (fg, bg) => ({
        r: fg.r * fg.a + bg.r * (1 - fg.a),
        g: fg.g * fg.a + bg.g * (1 - fg.a),
        b: fg.b * fg.a + bg.b * (1 - fg.a),
        a: 1,
    });
    const ratio = (a, b) => {
        const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x);
        return (hi + 0.05) / (lo + 0.05);
    };

    // The background actually painted behind an element: walk up compositing
    // translucent layers until something opaque is found.
    const bgOf = (el) => {
        let cur = el, acc = null;
        while (cur && cur.nodeType === 1) {
            const c = parse(getComputedStyle(cur).backgroundColor);
            if (c && c.a > 0) acc = acc ? over(acc, c) : c;
            if (acc && acc.a >= 0.999) return acc;
            cur = cur.parentElement;
        }
        return acc && acc.a >= 0.999 ? acc : { r: 255, g: 255, b: 255, a: 1 };
    };

    const name = (el) => {
        const cls = (el.getAttribute('class') || '').trim().slice(0, 60);
        return el.tagName.toLowerCase() + (cls ? `.${cls.split(/\s+/).join('.')}` : '');
    };

    const dark = document.documentElement.classList.contains('dark');
    // bg-ink is deliberately near-white in dark mode: it is the inverted button.
    const ink = parse(getComputedStyle(document.documentElement).getPropertyValue('--ink').trim());
    const near = (a, b) => a && b && Math.abs(a.r - b.r) < 3 && Math.abs(a.g - b.g) < 3 && Math.abs(a.b - b.b) < 3;

    const bright = [];
    const contrast = [];
    const seen = new Set();

    for (const el of document.querySelectorAll('body *')) {
        const box = el.getBoundingClientRect();
        if (box.width < 4 || box.height < 4) continue;
        const cs = getComputedStyle(el);
        if (cs.visibility === 'hidden' || cs.display === 'none' || Number(cs.opacity) === 0) continue;

        if (dark) {
            const own = parse(cs.backgroundColor);
            if (own && own.a > 0.85 && !near(own, ink) && lum(own) > 0.62) {
                const k = `b:${name(el)}`;
                if (!seen.has(k)) {
                    seen.add(k);
                    bright.push({ el: name(el), bg: cs.backgroundColor });
                }
            }
        }

        const text = Array.from(el.childNodes)
            .filter((n) => n.nodeType === Node.TEXT_NODE)
            .map((n) => n.textContent.trim())
            .join('');
        if (!text) continue;

        const fg = parse(cs.color);
        if (!fg) continue;
        const bg = bgOf(el);
        const cr = ratio(fg.a < 1 ? over(fg, bg) : fg, bg);
        const size = parseFloat(cs.fontSize);
        const large = size >= 24 || (size >= 18.66 && Number(cs.fontWeight) >= 700);
        const need = large ? 3 : 4.5;
        if (cr < need) {
            const k = `c:${name(el)}|${cs.color}`;
            if (!seen.has(k)) {
                seen.add(k);
                contrast.push({
                    el: name(el),
                    text: text.slice(0, 40),
                    ratio: Number(cr.toFixed(2)),
                    need,
                    color: cs.color,
                    size: cs.fontSize,
                });
            }
        }
    }
    return { bright, contrast };
};

for (const theme of ['light', 'dark']) {
    test(`${theme} theme: no unthemed surfaces and no text below WCAG AA`, async ({ page }) => {
        test.slow();
        await signIn(page);
        await page.evaluate((t) => localStorage.setItem('theme', t), theme);

        const problems = [];
        for (const path of PAGES) {
            await page.goto(path, { waitUntil: 'networkidle' });

            expect(
                await page.evaluate(() => document.documentElement.classList.contains('dark')),
                `${path} did not apply the ${theme} preference`,
            ).toBe(theme === 'dark');

            const { bright, contrast } = await page.evaluate(audit);
            for (const b of bright) {
                problems.push(`${path} unthemed background ${b.bg} on ${b.el}`);
            }
            for (const c of contrast) {
                problems.push(
                    `${path} contrast ${c.ratio}:1 (needs ${c.need}) — ${c.size} ${c.color} on "${c.text}" ${c.el}`,
                );
            }
        }

        expect(problems, `\n${problems.join('\n')}\n`).toEqual([]);
    });
}

/**
 * The signed-out pages, audited separately because signIn() is what the block
 * above starts with — and `/` redirects to the dashboard once you are signed
 * in, so the landing page would never be visited at all.
 *
 * It matters more than most: it is the first page anyone sees, it is the only
 * one seen before the app has been judged, and it is the one where a dark-mode
 * white flash or unreadable small print costs a signup rather than an
 * eyebrow-raise.
 */
const GUEST_PAGES = ['/', '/login', '/register'];

for (const theme of ['light', 'dark']) {
    test(`${theme} theme: signed-out pages are themed and readable`, async ({ page }) => {
        await page.addInitScript((t) => localStorage.setItem('theme', t), theme);

        const problems = [];
        for (const path of GUEST_PAGES) {
            await page.goto(path, { waitUntil: 'networkidle' });

            expect(
                await page.evaluate(() => document.documentElement.classList.contains('dark')),
                `${path} did not apply the ${theme} preference`,
            ).toBe(theme === 'dark');

            const { bright, contrast } = await page.evaluate(audit);
            for (const b of bright) {
                problems.push(`${path} unthemed background ${b.bg} on ${b.el}`);
            }
            for (const c of contrast) {
                problems.push(
                    `${path} contrast ${c.ratio}:1 (needs ${c.need}) — ${c.size} ${c.color} on "${c.text}" ${c.el}`,
                );
            }
        }

        expect(problems, `\n${problems.join('\n')}\n`).toEqual([]);
    });
}
