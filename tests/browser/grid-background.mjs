#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const pluginRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const workspaceRoot = resolve(pluginRoot, '../../../..');
const wpScript = join(workspaceRoot, 'scripts/wp');
const fixture = 'wp-content/plugins/million-dollar-script/tests/rewrite/grid-background-browser-fixture.php';
const browserPath = process.env.MDS_PLAYWRIGHT_BROWSER || '/usr/bin/brave';

function fixtureAction(action) {
    const php = `putenv('MDS_GRID_BACKGROUND_FIXTURE_ACTION=${action}'); include ABSPATH . '${fixture}';`;
    return execFileSync(wpScript, ['eval', php], {
        cwd: workspaceRoot,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'inherit'],
    }).trim();
}

function wpJsonEval(code) {
    const output = execFileSync(wpScript, ['eval', code], {
        cwd: workspaceRoot,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'inherit'],
    }).trim();
    return JSON.parse(output.split('\n').at(-1));
}

function authCookies() {
    return wpJsonEval(`
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
    const state = JSON.parse(fixtureAction('seed').split('\n').at(-1));
    const launchOptions = { headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] };
    if (existsSync(browserPath)) {
        launchOptions.executablePath = browserPath;
    }
    browser = await chromium.launch(launchOptions);
    const context = await browser.newContext({ viewport: { width: 1024, height: 768 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));

    const response = await page.goto(state.url, { waitUntil: 'networkidle' });
    if (!response || 200 !== response.status()) {
        throw new Error(`Grid background page returned ${response?.status() ?? 'no response'}.`);
    }
    const shell = page.locator(`.mds3-grid-shell[data-grid-id="${state.grid_id}"]`);
    await shell.waitFor({ state: 'visible' });
    await page.waitForFunction((gridId) => {
        const element = document.querySelector(`.mds3-grid-shell[data-grid-id="${gridId}"]`);
        return element?.mds3Grid?.backgroundImage?.complete
            && element.mds3Grid.backgroundImage.naturalWidth > 0;
    }, state.grid_id);

    const frontend = await shell.evaluate((element) => {
        const instance = element.mds3Grid;
        const config = instance.backgroundImageConfig();
        const canvas = document.createElement('canvas');
        canvas.width = 20;
        canvas.height = 20;
        const context = canvas.getContext('2d');
        context.fillStyle = '#123456';
        context.fillRect(0, 0, 20, 20);
        instance.drawGridBackgroundImage(context, { offsetX: 0, offsetY: 0, scale: 0.1 });
        const pixel = Array.from(context.getImageData(10, 10, 1, 1).data);

        return {
            config,
            hasRemoteTiles: instance.hasRemoteTiles(),
            pixel,
            backgroundColor: getComputedStyle(element).getPropertyValue('--mds3-grid-bg').trim(),
        };
    });
    if (
        frontend.hasRemoteTiles
        || frontend.config?.fit !== 'cover'
        || frontend.config?.position !== 'center'
        || frontend.config?.repeat !== 'no-repeat'
        || frontend.config?.opacity !== 100
    ) {
        throw new Error('Frontend grid did not preserve the local background presentation contract.');
    }
    if (frontend.backgroundColor.toLowerCase() !== '#123456') {
        throw new Error('Frontend grid did not preserve its background color fallback.');
    }
    if (frontend.pixel[0] < 240 || frontend.pixel[1] > 20 || frontend.pixel[2] > 20 || frontend.pixel[3] !== 255) {
        throw new Error(`Grid background image was not painted beneath the canvas layers: ${frontend.pixel.join(',')}`);
    }

    await context.addCookies(authCookies());
    const admin = await context.newPage();
    admin.on('pageerror', (error) => errors.push(error.message));
    await admin.goto(state.admin_url, { waitUntil: 'networkidle' });
    if (await admin.locator('h3', { hasText: 'Grid Background' }).count() !== 1) {
        throw new Error('Grid editor did not expose the Grid Background section.');
    }
    if (await admin.locator('input[name="background_image_id"]').inputValue() !== String(state.attachment_id)) {
        throw new Error('Grid editor did not retain the selected background attachment.');
    }
    if (await admin.locator('.mds3-image-field-preview-image').count() !== 1) {
        throw new Error('Grid editor did not show the selected background preview.');
    }
    if (await admin.locator('input[name="background_image_opacity"]').getAttribute('max') !== '100') {
        throw new Error('Grid editor did not bound background opacity.');
    }
    if (errors.length) {
        throw new Error(`Browser errors: ${errors.join('; ')}`);
    }

    console.log('Grid background browser fixture passed.');
} finally {
    if (browser) {
        await browser.close();
    }
    fixtureAction('cleanup');
}
