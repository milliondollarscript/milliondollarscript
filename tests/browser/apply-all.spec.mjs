/**
 * Regression check for the "Apply to all modified pages" control in the MDS2
 * page-upgrade accordion (setup wizard and migration screen).
 *
 * The accordion markup and its inline script are rendered by
 * templates/admin/partials/mds2-page-upgrade-accordion.php. The fixture below
 * mirrors the rendered shape (one locked unmodified page, two modified pages);
 * the script under test is read out of that template at run time, so editing
 * the template changes this check.
 *
 * Run from the plugin repo root with the workspace Playwright install:
 *   node tests/browser/apply-all.spec.mjs
 * Override the install with PLAYWRIGHT_PATH=/path/to/playwright/index.mjs.
 */
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const template = fs.readFileSync(path.join(repoRoot, 'templates/admin/partials/mds2-page-upgrade-accordion.php'), 'utf8');

const script = template.match(/<script>([\s\S]*?)<\/script>/);
if (!script) {
    throw new Error('accordion template has no inline script');
}
// The only PHP in the script is the list of extra form ids; none here.
const js = script[1].replace(/<\?php[\s\S]*?\?>/, '[]');

const markup = `
<div class="mds3-upgrade-accordion">
  <label class="mds3-upgrade-accordion__parent" for="apply-all">
    <input type="checkbox" id="apply-all" data-upgrade-parent />
    <strong>Apply to all modified pages</strong>
  </label>
  <details class="mds3-upgrade-accordion__group" open>
    <summary>Detected Million Dollar Script 2 pages</summary>
    <details class="mds3-upgrade-accordion__item is-locked" open>
      <summary><input type="checkbox" name="mds2_upgrade_pages[]" value="1" data-upgrade-check checked disabled /></summary>
    </details>
    <details class="mds3-upgrade-accordion__item" open>
      <summary><input type="checkbox" name="mds2_upgrade_pages[]" value="2" data-upgrade-check /></summary>
    </details>
    <details class="mds3-upgrade-accordion__item" open>
      <summary><input type="checkbox" name="mds2_upgrade_pages[]" value="3" data-upgrade-check /></summary>
    </details>
  </details>
  <script>${js}</script>
</div>`;

const pagePath = path.join(os.tmpdir(), 'mds3-apply-all-fixture.html');
fs.writeFileSync(pagePath, `<!doctype html><meta charset="utf-8"><body>${markup}</body>`);

const playwrightPath = process.env.PLAYWRIGHT_PATH
    ?? '/home/rwr/projects/mds-workspace/repos/extension-server-go/node_modules/playwright/index.mjs';
const { chromium } = await import(pathToFileURL(playwrightPath).href);

const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto(pathToFileURL(pagePath).href);

const state = () => page.evaluate(() => ({
    parent: document.querySelector('[data-upgrade-parent]').checked,
    indeterminate: document.querySelector('[data-upgrade-parent]').indeterminate,
    kids: [...document.querySelectorAll('[data-upgrade-check]:not(:disabled)')].map((c) => c.checked),
    open: [...document.querySelectorAll('.mds3-upgrade-accordion__item')].map((d) => d.open),
}));

const failures = [];
const check = (label, got, want) => {
    const ok = JSON.stringify(got) === JSON.stringify(want);
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${label}: ${JSON.stringify(got)}${ok ? '' : ` want ${JSON.stringify(want)}`}`);
    if (!ok) {
        failures.push(label);
    }
};

check('initial', await state(), { parent: false, indeterminate: false, kids: [false, false], open: [true, true, true] });

// Users click the words, not the 13px box: the label must not double-toggle.
await page.click('.mds3-upgrade-accordion__parent');
check('apply all', await state(), { parent: true, indeterminate: false, kids: [true, true], open: [true, true, true] });

// A per-page change syncs the parent, and must not collapse the disclosure.
await page.click('[data-upgrade-check]:not(:disabled)');
check('one page unchecked', await state(), { parent: false, indeterminate: true, kids: [false, true], open: [true, true, true] });

await page.click('.mds3-upgrade-accordion__parent');
check('from indeterminate', await state(), { parent: true, indeterminate: false, kids: [true, true], open: [true, true, true] });

await page.click('.mds3-upgrade-accordion__parent');
check('clear all', await state(), { parent: false, indeterminate: false, kids: [false, false], open: [true, true, true] });

await page.click('[data-upgrade-parent]');
check('direct parent click', await state(), { parent: true, indeterminate: false, kids: [true, true], open: [true, true, true] });

await browser.close();
console.log(failures.length ? 'FAILURES: ' + failures.join(', ') : 'ALL OK');
process.exit(failures.length ? 1 : 0);