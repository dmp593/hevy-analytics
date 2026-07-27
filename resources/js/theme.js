/**
 * Light / dark theme.
 *
 * The class must be on <html> BEFORE first paint, otherwise a dark-mode user
 * gets a white flash on every navigation — which is worse than not offering the
 * feature. `apply()` is therefore also inlined in the layout head; this module
 * only handles switching once the page is interactive.
 *
 * Three states, not two: "system" is a real choice and the default, so the app
 * follows the OS until someone deliberately overrides it.
 */

const KEY = 'theme';

export function resolve(preference) {
    if (preference === 'light' || preference === 'dark') {
        return preference;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function apply(preference) {
    const dark = resolve(preference) === 'dark';

    document.documentElement.classList.toggle('dark', dark);

    // The browser chrome (phone status bar, PWA title bar) reads theme-color
    // from <meta>, which media queries pin to the OS scheme — so a manual
    // in-app override needs the metas rewritten or the bar stays mismatched.
    // Values are the two --canvas tokens from app.css.
    for (const meta of document.querySelectorAll('meta[name="theme-color"]')) {
        meta.setAttribute('content', dark ? '#0c1015' : '#f9fafb');
        meta.removeAttribute('media');
    }
}

export default function theme() {
    return {
        preference: localStorage.getItem(KEY) || 'system',

        init() {
            apply(this.preference);

            // Someone on "system" should follow the OS live, not only on reload.
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (this.preference === 'system') {
                    apply(this.preference);
                }
            });
        },

        choose(preference) {
            this.preference = preference;
            localStorage.setItem(KEY, preference);
            apply(preference);
        },

        get isDark() {
            return resolve(this.preference) === 'dark';
        },
    };
}
