#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const __dirname = dirname(fileURLToPath(import.meta.url));
const pluginRoot = resolve(__dirname, '../..');
const workspaceRoot = resolve(pluginRoot, '../../../..');
const wpScript = join(workspaceRoot, 'scripts/wp');
const browserPath = process.env.MDS_PLAYWRIGHT_BROWSER || '/usr/bin/brave';

function wpJsonEval(code) {
    const output = execFileSync(wpScript, ['eval', code], {
        cwd: workspaceRoot,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();

    return JSON.parse(output);
}

function setModes(urlMode, textMode) {
    wpJsonEval(`
        $settings = get_option('mds3_settings', array());
        $settings = is_array($settings) ? $settings : array();
        $settings['url-optional'] = '${urlMode}';
        $settings['text-optional'] = '${textMode}';
        update_option('mds3_settings', $settings, false);
        echo wp_json_encode(array('ok' => true));
    `);
}

function authCookies() {
    return wpJsonEval(`
        $users = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        $user_id = $users ? absint($users[0]) : 0;
        $expiration = time() + HOUR_IN_SECONDS;
        $scheme = is_ssl() ? 'secure_auth' : 'auth';
        $auth_name = ('secure_auth' === $scheme) ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $secure = is_ssl();
        echo wp_json_encode(array(
            array('name' => $auth_name, 'value' => wp_generate_auth_cookie($user_id, $expiration, $scheme), 'domain' => $host, 'path' => '/', 'httpOnly' => true, 'secure' => $secure, 'sameSite' => 'Lax'),
            array('name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($user_id, $expiration, 'logged_in'), 'domain' => $host, 'path' => '/', 'httpOnly' => true, 'secure' => $secure, 'sameSite' => 'Lax')
        ));
    `);
}

let fixture = null;
let browser = null;

try {
    fixture = wpJsonEval(`
        $grid = (new \\MillionDollarScript\\V3\\Grid\\GridRepository())->first_active();
        if (!$grid) {
            echo wp_json_encode(array('error' => 'missing_grid'));
            return;
        }
        $original = get_option('mds3_settings', array());
        $original = is_array($original) ? $original : array();
        $settings = $original;
        $settings['url-optional'] = 'hidden';
        $settings['text-optional'] = 'hidden';
        update_option('mds3_settings', $settings, false);
        $post_id = wp_insert_post(array(
            'post_title' => 'Placement Field Contract Browser Fixture',
            'post_content' => '[mds_grid id="' . $grid->id() . '" read_only="false"]',
            'post_status' => 'publish',
            'post_type' => 'page',
        ));
        echo wp_json_encode(array(
            'post_id' => absint($post_id),
            'url' => get_permalink($post_id),
            'original' => base64_encode(wp_json_encode($original)),
        ));
    `);

    if (fixture.error || !fixture.post_id || !fixture.url) {
        throw new Error('Could not create the placement field browser fixture.');
    }

    const launchOptions = { headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] };
    if (existsSync(browserPath)) {
        launchOptions.executablePath = browserPath;
    }
    browser = await chromium.launch(launchOptions);
    const context = await browser.newContext({ viewport: { width: 375, height: 667 }, isMobile: true });
    await context.addCookies(authCookies());
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await page.goto(fixture.url, { waitUntil: 'networkidle' });
    if (0 !== await page.locator('input[name="link_url"]').count()) {
        throw new Error('Hidden advertiser URL field rendered on the customer form.');
    }
    if (0 !== await page.locator('textarea[name="popup_text"]').count()) {
        throw new Error('Hidden popup text field rendered on the customer form.');
    }

    setModes('no', 'no');
    await page.reload({ waitUntil: 'networkidle' });
    const urlInput = page.locator('input[name="link_url"]');
    const popupInput = page.locator('textarea[name="popup_text"]');
    if (1 !== await urlInput.count() || !await urlInput.evaluate((input) => input.required)) {
        throw new Error('Required advertiser URL field did not render with browser validation.');
    }
    if (1 !== await popupInput.count() || !await popupInput.evaluate((input) => input.required)) {
        throw new Error('Required popup text field did not render with browser validation.');
    }
    if (await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2)) {
        throw new Error('Placement form introduced horizontal overflow at 375 pixels.');
    }

    const adminPage = await context.newPage();
    adminPage.on('pageerror', (error) => pageErrors.push(error.message));
    const settingsUrl = new URL('/wp-admin/admin.php?page=mds3-settings&tab=display-interaction', fixture.url).toString();
    await adminPage.goto(settingsUrl, { waitUntil: 'networkidle' });
    const fieldsBuilderCallout = adminPage.locator('.mds-fields-core-popup-template-callout');
    const corePreview = adminPage.locator('[data-mds3-popup-layout-preview]');
    if (await fieldsBuilderCallout.count()) {
        if (await corePreview.count()) {
            throw new Error('Core popup preview rendered beneath the active Fields Popup Builder handoff.');
        }
    } else if (1 !== await corePreview.count()) {
        throw new Error('Core popup layout preview did not render when Popup Builder was unavailable.');
    }

    const orderSettingsUrl = new URL('/wp-admin/admin.php?page=mds3-settings&tab=orders-uploads', fixture.url).toString();
    await adminPage.goto(orderSettingsUrl, { waitUntil: 'networkidle' });
    for (const fieldName of ['url-optional', 'text-optional']) {
        const labels = await adminPage.locator(`select[name="${fieldName}"] option`).allTextContents();
        const normalized = labels.map((label) => label.trim());
        if (JSON.stringify(normalized) !== JSON.stringify(['Required', 'Optional', 'Hidden'])) {
            throw new Error(`Placement field state options were incomplete for ${fieldName}.`);
        }
    }

    const previewPage = await context.newPage();
    previewPage.on('pageerror', (error) => pageErrors.push(error.message));
    await previewPage.setContent(`
        <form>
            <textarea name="popup-template"></textarea>
            <div data-mds3-popup-layout-preview data-built-in-label="Built-in layout" data-custom-label="Custom layout">
                <span data-mds3-popup-preview-mode></span>
                <div>
                    <span data-mds3-popup-preview-part="image">Image</span>
                    <span data-mds3-popup-preview-part="alt_text">Title</span>
                    <span data-mds3-popup-preview-part="url">URL</span>
                    <span data-mds3-popup-preview-part="text">Text</span>
                    <span data-mds3-popup-preview-part="custom">Custom</span>
                    <span data-mds3-popup-preview-fallback hidden>Fallback</span>
                </div>
            </div>
        </form>
    `);
    await previewPage.addScriptTag({ path: join(pluginRoot, 'assets/mds3/js/admin.js') });
    await previewPage.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    const templateInput = previewPage.locator('textarea[name="popup-template"]');
    await templateInput.fill('<div>%text%</div>');
    if (await previewPage.locator('[data-mds3-popup-preview-part="text"]').isHidden()) {
        throw new Error('Popup layout preview did not show a selected placeholder.');
    }
    if (!await previewPage.locator('[data-mds3-popup-preview-part="url"]').isHidden()) {
        throw new Error('Popup layout preview showed a placeholder absent from the template.');
    }
    await templateInput.fill('<div></div>');
    if (await previewPage.locator('[data-mds3-popup-preview-fallback]').isHidden()) {
        throw new Error('Popup layout preview did not explain the accessible fallback for empty output.');
    }

    if (pageErrors.length) {
        throw new Error(`Browser errors: ${pageErrors.join('; ')}`);
    }

    console.log('Placement field contract browser test passed.');
} finally {
    if (browser) {
        await browser.close();
    }
    if (fixture?.post_id && fixture?.original) {
        const postId = Number.parseInt(fixture.post_id, 10);
        const original = String(fixture.original).replace(/[^A-Za-z0-9+/=]/g, '');
        wpJsonEval(`
            $settings = json_decode(base64_decode('${original}'), true);
            update_option('mds3_settings', is_array($settings) ? $settings : array(), false);
            wp_delete_post(${postId}, true);
            echo wp_json_encode(array('ok' => true));
        `);
    }
}
