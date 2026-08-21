import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const workspace = '/home/rwr/projects/mds-workspace';
const fixture = 'wp-content/plugins/million-dollar-script/tests/rewrite/advertiser-page-browser-fixture.php';

function fixtureAction(action) {
    const php = `putenv('MDS_ADVERTISER_FIXTURE_ACTION=${action}'); include ABSPATH . '${fixture}';`;
    return execFileSync(`${workspace}/scripts/wp`, ['eval', php], {
        cwd: workspace,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'inherit'],
    }).trim();
}

let browser;
try {
    const seedOutput = fixtureAction('seed');
    const state = JSON.parse(seedOutput.split('\n').at(-1));
    browser = await chromium.launch({
        headless: true,
        executablePath: process.env.MDS_BROWSER_EXECUTABLE || '/usr/bin/brave',
    });
    const page = await browser.newPage({ viewport: { width: 375, height: 667 } });
    const response = await page.goto(state.url, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) {
        throw new Error(`Advertiser page returned ${response?.status() ?? 'no response'}`);
    }
    await page.locator('.mds-advertiser-page').waitFor({ state: 'visible' });
    if (await page.locator('h1').count() !== 1 || await page.locator('h1').textContent() !== 'Browser Fixture Advertiser') {
        throw new Error('Advertiser page heading did not match the public placement title.');
    }
    const body = await page.locator('body').innerText();
    if (body.includes('browser-private@example.test') || body.includes(String(state.placement_id) + ' order')) {
        throw new Error('Advertiser page exposed private fixture data.');
    }
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    if (overflow) {
        throw new Error('Advertiser page overflows a 375px mobile viewport.');
    }
    const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
    if (canonical !== state.url) {
        throw new Error(`Unexpected canonical URL: ${canonical}`);
    }
    if (await page.locator('meta[property="og:title"]').count() !== 1) {
        throw new Error('Advertiser page must emit exactly one Open Graph title.');
    }
    if (await page.locator('script[type="application/ld+json"]').count() < 1) {
        throw new Error('Advertiser page did not emit structured data.');
    }
    console.log('Advertiser page browser fixture passed.');
} finally {
    if (browser) {
        await browser.close();
    }
    fixtureAction('cleanup');
}
