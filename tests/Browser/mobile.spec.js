import { expect, test } from '@playwright/test';
import { signIn } from './helpers.js';

/**
 * Mobile ergonomics guard.
 *
 * Found the hard way: the whole app passed the desktop suite while shipping
 * 16px tap targets and 10px text to phones. Layout held; ergonomics did not.
 * This audit walks every page at 390px and fails on the three things a desktop
 * check cannot see:
 *
 *   1. sideways scroll (with no scroll container to justify it)
 *   2. tap targets under 40px on standalone interactive elements
 *   3. text under 11px
 */

test.use({
    viewport: { width: 390, height: 844 },
    hasTouch: true,
    isMobile: true,
});

const GUEST_PAGES = ['/', '/login', '/register'];
const PAGES = [
    '/dashboard', '/performance', '/strength-levels', '/muscle', '/body',
    '/photos', '/nutrition', '/projections', '/routines', '/goals',
    '/ai', '/guide', '/write-operations', '/profile', '/import', '/billing',
];

/** Runs in the page; returns everything that breaks a rule. */
const audit = () => {
    const vw = document.documentElement.clientWidth;
    const problems = [];

    const name = (el) => el.tagName.toLowerCase()
        + (el.id ? '#' + el.id : '')
        + (typeof el.className === 'string' && el.className.trim()
            ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.')
            : '');

    if (document.documentElement.scrollWidth > vw) {
        problems.push(`page scrolls sideways by ${document.documentElement.scrollWidth - vw}px`);
    }

    for (const el of document.querySelectorAll('body *')) {
        const cs = getComputedStyle(el);
        if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) === 0) {
            continue;
        }
        const r = el.getBoundingClientRect();
        if (r.width < 1 || r.height < 1) continue;

        // --- tap targets ---------------------------------------------------
        const tag = el.tagName.toLowerCase();
        const interactive = tag === 'button' || tag === 'select'
            || (tag === 'a' && el.href)
            || (tag === 'input' && el.type !== 'hidden');

        if (interactive) {
            // Screen-reader-only elements are 1x1 BY DESIGN — they exist for
            // keyboards and assistive tech, and grow to full size on focus.
            if (el.classList.contains('sr-only')) continue;

            // Inline links inside running prose are exempt (every tap-target
            // guideline exempts them): their size is the sentence's business.
            const inProse = el.closest('p, li, td, dd') !== null && tag === 'a';

            // A tiny control wrapped in a big label is fine — the label taps.
            const label = el.closest('label');
            const target = label ? label.getBoundingClientRect() : r;

            if (! inProse && target.height < 40 && target.width < 200) {
                problems.push(`tap target ${Math.round(target.width)}x${Math.round(target.height)}: ${name(el)} "${(el.textContent || '').trim().slice(0, 25)}"`);
            }
            if (! inProse && target.height < 40 && target.width >= 200) {
                // Wide-but-short is still hard to hit vertically.
                if (target.height < 32) {
                    problems.push(`short tap target h=${Math.round(target.height)}: ${name(el)}`);
                }
            }
        }

        // --- text size -----------------------------------------------------
        const hasText = Array.from(el.childNodes).some(
            (n) => n.nodeType === Node.TEXT_NODE && n.textContent.trim(),
        );
        if (hasText && parseFloat(cs.fontSize) < 11) {
            problems.push(`text at ${cs.fontSize}: ${name(el)} "${(el.textContent || '').trim().slice(0, 30)}"`);
        }
    }

    return [...new Set(problems)];
};

test('signed-out pages hold up on a phone', async ({ page }) => {
    const problems = [];

    for (const path of GUEST_PAGES) {
        await page.goto(path, { waitUntil: 'networkidle' });
        for (const p of await page.evaluate(audit)) {
            problems.push(`${path}: ${p}`);
        }
    }

    expect(problems, `\n${problems.join('\n')}\n`).toEqual([]);
});

test('signed-in pages hold up on a phone', async ({ page }) => {
    test.slow();
    await signIn(page);

    const problems = [];

    for (const path of PAGES) {
        await page.goto(path, { waitUntil: 'networkidle' });
        for (const p of await page.evaluate(audit)) {
            problems.push(`${path}: ${p}`);
        }
    }

    expect(problems, `\n${problems.join('\n')}\n`).toEqual([]);
});
