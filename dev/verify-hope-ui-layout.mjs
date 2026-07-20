import { chromium } from 'playwright';

const baseUrl = process.env.PSM_BASE_URL;
const username = process.env.PSM_TEST_USER;
const password = process.env.PSM_TEST_PASSWORD;
if (!baseUrl || !username || !password) {
  console.error('PSM_BASE_URL, PSM_TEST_USER and PSM_TEST_PASSWORD are required.');
  process.exit(2);
}

const widths = [360, 390, 768, 1024, 1366, 1600];
const themes = ['light', 'dark'];
const routes = [
  'index.php?mod=server_status', 'index.php?mod=server_statistics', 'index.php?mod=server',
  'index.php?mod=server_log', 'index.php?mod=user', 'index.php?mod=config',
  'index.php?mod=config_system', 'index.php?mod=server_update', 'index.php?mod=user_profile',
  'index.php?mod=user_notification',
];

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
const failures = [];
try {
  await page.goto(new URL('index.php', baseUrl).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('[name="user_name"]').fill(username);
  await page.locator('[name="user_password"]').fill(password);
  await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('button[type="submit"]').click()]);
  if (await page.locator('[name="user_password"]').count()) { throw new Error('Login failed.'); }

  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of routes) {
      await page.goto(new URL(route, baseUrl).toString(), { waitUntil: 'networkidle' });
      if (!await page.locator('[data-theme-quick-toggle]').count() || !await page.locator('#hope-ui-settings').count()) {
        failures.push({ route, width, error: 'Missing theme controls' });
        continue;
      }
      for (const expectedTheme of themes) {
        const currentTheme = await page.evaluate(() => document.documentElement.dataset.bsTheme);
        if (currentTheme !== expectedTheme) { await page.locator('[data-theme-quick-toggle]').first().click(); }
        await page.waitForFunction((theme) => document.documentElement.dataset.bsTheme === theme, expectedTheme);
        const result = await page.evaluate(() => {
          const viewport = document.documentElement.clientWidth;
          const overflows = [...document.querySelectorAll('body *')].map((element) => {
            const style = getComputedStyle(element);
            if (style.display === 'none' || style.visibility === 'hidden' || style.position === 'fixed') { return null; }
            const box = element.getBoundingClientRect();
            return { selector: element.id || String(element.className || element.tagName), left: box.left, right: box.right };
          }).filter((item) => item && (item.left < -1 || item.right > viewport + 1)).slice(0, 20);
          return { viewport, scrollWidth: document.documentElement.scrollWidth, overflows, title: document.title };
        });
        if (result.scrollWidth > result.viewport || result.overflows.length || /error|fatal/i.test(result.title)) {
          failures.push({ route, width, theme: expectedTheme, ...result });
        }
        console.log(JSON.stringify({ route, width, theme: expectedTheme, ...result }));
      }
    }
  }
} finally {
  await browser.close();
}

if (failures.length) {
  console.error(JSON.stringify({ failures }, null, 2));
  process.exit(1);
}
