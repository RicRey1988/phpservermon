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

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(new URL('index.php?mod=server_status', baseUrl).toString(), { waitUntil: 'networkidle' });
  const mobileToggle = page.locator('[data-sidebar-mobile-toggle]');
  const mobileSidebar = page.locator('.sidebar.sidebar-base');
  const mobileBackdrop = page.locator('[data-sidebar-backdrop]');
  await mobileToggle.click();
  await page.waitForFunction(() => !document.body.classList.contains('sidebar-mini'));
  const openedDrawer = await page.evaluate(() => {
    const sidebar = document.querySelector('.sidebar.sidebar-base');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const box = sidebar.getBoundingClientRect();
    return {
      left: box.left,
      right: box.right,
      backdropHidden: backdrop.hidden,
      expanded: document.querySelector('[data-sidebar-mobile-toggle]').getAttribute('aria-expanded'),
      duplicateAccount: Boolean(document.querySelector('.sidebar-user')),
    };
  });
  if (openedDrawer.left < -1 || openedDrawer.right <= 1 || openedDrawer.backdropHidden || openedDrawer.expanded !== 'true' || openedDrawer.duplicateAccount) {
    failures.push({ route: 'mobile-drawer-open', width: 390, ...openedDrawer });
  }
  await mobileBackdrop.click({ position: { x: 385, y: 420 } });
  await page.waitForFunction(() => document.body.classList.contains('sidebar-mini'));
  const closedDrawer = await page.evaluate(() => ({
    right: document.querySelector('.sidebar.sidebar-base').getBoundingClientRect().right,
    backdropHidden: document.querySelector('[data-sidebar-backdrop]').hidden,
    expanded: document.querySelector('[data-sidebar-mobile-toggle]').getAttribute('aria-expanded'),
  }));
  if (closedDrawer.right > 1 || !closedDrawer.backdropHidden || closedDrawer.expanded !== 'false') {
    failures.push({ route: 'mobile-drawer-closed', width: 390, ...closedDrawer });
  }

  await page.setViewportSize({ width: 1366, height: 900 });
  await page.goto(new URL('index.php?mod=server_status', baseUrl).toString(), { waitUntil: 'networkidle' });
  const topbarAlignment = await page.evaluate(() => {
    const boxes = [...document.querySelectorAll('.topbar-icon-button')]
      .filter((element) => getComputedStyle(element).display !== 'none')
      .map((element) => element.getBoundingClientRect())
      .map((box) => ({ width: box.width, height: box.height, middle: box.top + (box.height / 2) }));
    const middle = boxes[0]?.middle || 0;
    return { boxes, maxMiddleDelta: Math.max(0, ...boxes.map((box) => Math.abs(box.middle - middle))) };
  });
  if (!topbarAlignment.boxes.length || topbarAlignment.boxes.some((box) => Math.abs(box.width - 44) > 1 || Math.abs(box.height - 44) > 1) || topbarAlignment.maxMiddleDelta > 1) {
    failures.push({ route: 'topbar-alignment', width: 1366, ...topbarAlignment });
  }

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
