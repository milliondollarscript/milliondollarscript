#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const pluginRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const workspaceRoot = resolve(pluginRoot, '../../../..');
const wpScript = join(workspaceRoot, 'scripts/wp');
const fixture = 'wp-content/plugins/million-dollar-script/tests/rewrite/order-admin-controls-browser-fixture.php';
const browserPath = process.env.MDS_PLAYWRIGHT_BROWSER || '/usr/bin/brave';

function wpEval(code) {
    const output = execFileSync(wpScript, ['eval', code], {
        cwd: workspaceRoot,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'inherit'],
    }).trim();
    return JSON.parse(output.split('\n').at(-1));
}

function fixtureAction(action) {
    return wpEval(`putenv('MDS_ORDER_ADMIN_CONTROLS_FIXTURE_ACTION=${action}'); include ABSPATH . '${fixture}';`);
}

function authCookies() {
    return wpEval(`
        $users = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        $user_id = $users ? absint($users[0]) : 0;
        $expiration = time() + HOUR_IN_SECONDS;
        $scheme = is_ssl() ? 'secure_auth' : 'auth';
        $auth_name = ('secure_auth' === $scheme) ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        echo wp_json_encode(array(
            array('name' => $auth_name, 'value' => wp_generate_auth_cookie($user_id, $expiration, $scheme), 'domain' => $host, 'path' => '/', 'httpOnly' => true, 'secure' => is_ssl(), 'sameSite' => 'Lax'),
            array('name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($user_id, $expiration, 'logged_in'), 'domain' => $host, 'path' => '/', 'httpOnly' => true, 'secure' => is_ssl(), 'sameSite' => 'Lax')
        ));
    `);
}

let browser;
try {
    const state = fixtureAction('seed');
    const launchOptions = { headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] };
    if (existsSync(browserPath)) {
        launchOptions.executablePath = browserPath;
    }
    browser = await chromium.launch(launchOptions);
    const context = await browser.newContext({ viewport: { width: 375, height: 667 } });
    await context.addCookies(authCookies());
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));

    const response = await page.goto(state.url, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) {
        throw new Error(`Orders admin returned ${response?.status() ?? 'no response'}.`);
    }

    const row = page.locator('.mds3-orders-table tbody tr', { hasText: state.email }).first();
    const inspect = row.locator('.mds3-order-inspect');
    const controlID = `mds3-order-detail-${state.order_id}`;
    if (await inspect.getAttribute('aria-expanded') !== 'false' || await inspect.getAttribute('aria-controls') !== controlID) {
        throw new Error('Inspect did not expose the collapsed disclosure contract.');
    }
    const collapsedTransform = await inspect.locator('.mds3-order-inspect-indicator').evaluate((element) => getComputedStyle(element).transform);
    const initialBox = await inspect.boundingBox();
    await inspect.click();
    await page.locator(`#${controlID}`).waitFor({ state: 'visible' });
    if (await inspect.getAttribute('aria-expanded') !== 'true') {
        throw new Error('Inspect did not announce the expanded state.');
    }
    const expandedTransform = await inspect.locator('.mds3-order-inspect-indicator').evaluate((element) => getComputedStyle(element).transform);
    if (collapsedTransform === expandedTransform) {
        throw new Error('Inspect indicator did not change direction.');
    }
    await inspect.press('Enter');
    await page.locator(`#${controlID}`).waitFor({ state: 'detached' });
    const finalBox = await inspect.boundingBox();
    if (await inspect.getAttribute('aria-expanded') !== 'false' || !initialBox || !finalBox || Math.abs(initialBox.width - finalBox.width) > 1 || Math.abs(initialBox.height - finalBox.height) > 1) {
        throw new Error('Inspect toggling changed its control dimensions or collapsed state.');
    }

    const cancelForm = row.locator('form:has(input[name="status"][value="cancelled"])');
    const cancelButton = cancelForm.locator('button[type="submit"]');
    let dismissMessage = '';
    page.once('dialog', async (dialog) => {
        dismissMessage = dialog.message();
        await dialog.dismiss();
    });
    await cancelButton.click();
    if (!dismissMessage.includes(`#${state.order_id}`) || !dismissMessage.includes('keep it unchanged')) {
        throw new Error('Cancellation confirmation did not identify the order and safe action.');
    }
    if (fixtureAction('status').status !== 'pending_payment') {
        throw new Error('Dismissing cancellation changed the order status.');
    }

    page.once('dialog', (dialog) => dialog.accept());
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        cancelButton.click(),
    ]);
    if (fixtureAction('status').status !== 'cancelled') {
        throw new Error('Confirming cancellation did not change the order status.');
    }
    if (errors.length) {
        throw new Error(`Browser errors: ${errors.join('; ')}`);
    }

    console.log('Orders admin controls browser fixture passed.');
} finally {
    if (browser) {
        await browser.close();
    }
    fixtureAction('cleanup');
}
