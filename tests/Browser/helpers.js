/**
 * Shared browser-test helpers.
 *
 * signIn lives here because the obvious selector is a trap: the language
 * switcher renders a <form> per language, each with its own submit button, and
 * those sit in the DOM (hidden) on the login page. A bare
 * `button[type=submit]` matches one of those first and the whole suite times
 * out. Scope to the login form.
 */

export const DEMO = { email: 'demo@example.test', password: 'password' };

export async function signIn(page, { email = DEMO.email, password = DEMO.password } = {}) {
    await page.goto('/login');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await page.locator('form[action$="/login"] button[type=submit]').click();
    await page.waitForURL('**/dashboard');
}
