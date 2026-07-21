import { chromium } from 'playwright';
import { readFile } from 'node:fs/promises';

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

async function auditNavigationChrome(page, route, width, theme) {
  const evidence = await page.evaluate(() => {
    const centerY = (element) => {
      const box = element?.getBoundingClientRect();
      return box ? box.top + box.height / 2 : null;
    };
    const quick = document.querySelector('[data-theme-quick-toggle]');
    const settings = document.querySelector('[data-bs-target="#hope-ui-settings"]');
    const notifications = document.querySelector('[data-notification-navbar] button');
    const centers = [quick, settings, notifications].filter(Boolean).map(centerY);
    const visibleThemeIcons = quick ? [...quick.querySelectorAll('[data-theme-icon]')].filter((element) => {
      const style = getComputedStyle(element);
      const box = element.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity) > 0 && box.width > 0 && box.height > 0;
    }).length : 0;
    const search = document.querySelector('.search-input');
    const searchInput = search?.querySelector('input.form-control');
    const searchStyle = searchInput ? getComputedStyle(searchInput) : null;
    return {
      visibleThemeIcons,
      duplicateSidebarSession: document.querySelectorAll('.sidebar-user').length,
      alignedNavbarControls: centers.length >= 2 && Math.max(...centers) - Math.min(...centers) <= 2,
      searchStructure: !search || Boolean(search.querySelector('.input-group-text') && searchInput),
      searchThemeSafe: !searchStyle || (searchStyle.backgroundColor !== 'rgba(0, 0, 0, 0)' && searchStyle.color !== searchStyle.backgroundColor),
    };
  });

  const issues = [];
  if (evidence.visibleThemeIcons !== 1) issues.push('visibleThemeIcons');
  if (evidence.duplicateSidebarSession !== 0) issues.push('duplicateSidebarSession');
  if (!evidence.alignedNavbarControls) issues.push('alignedNavbarControls');
  if (!evidence.searchStructure) issues.push('searchStructure');
  if (!evidence.searchThemeSafe) issues.push('searchThemeSafe');

  if (width < 1200) {
    const mobileToggle = page.locator('.iq-navbar [data-toggle="sidebar"]').first();
    const sidebar = page.locator('.sidebar-default').first();
    if (!await mobileToggle.count() || !await sidebar.count()) {
      issues.push('mobileSidebar');
    } else {
      await mobileToggle.click();
      await page.waitForFunction(() => {
        const sidebarElement = document.querySelector('.sidebar-default');
        const toggle = document.querySelector('.iq-navbar [data-toggle="sidebar"]');
        return sidebarElement && !sidebarElement.classList.contains('sidebar-mini') && toggle?.getAttribute('aria-expanded') === 'true';
      });
      await page.mouse.click(width - 5, 200);
      await page.waitForFunction(() => {
        const sidebarElement = document.querySelector('.sidebar-default');
        const toggle = document.querySelector('.iq-navbar [data-toggle="sidebar"]');
        return sidebarElement?.classList.contains('sidebar-mini') && toggle?.getAttribute('aria-expanded') === 'false';
      });
      await mobileToggle.click();
      await page.waitForFunction(() => {
        const sidebarElement = document.querySelector('.sidebar-default');
        const toggle = document.querySelector('.iq-navbar [data-toggle="sidebar"]');
        return sidebarElement && !sidebarElement.classList.contains('sidebar-mini') && toggle?.getAttribute('aria-expanded') === 'true';
      });
      await page.keyboard.press('Escape');
      await page.waitForFunction(() => {
        const sidebarElement = document.querySelector('.sidebar-default');
        const toggle = document.querySelector('.iq-navbar [data-toggle="sidebar"]');
        return sidebarElement?.classList.contains('sidebar-mini') && toggle?.getAttribute('aria-expanded') === 'false';
      });
    }
  } else {
    const desktopToggle = page.locator('.sidebar-header [data-toggle="sidebar"]').first();
    if (!await desktopToggle.count()) {
      issues.push('desktopSidebar');
    } else {
      const expandedTransform = await desktopToggle.locator('.icon').evaluate((element) => getComputedStyle(element).transform);
      await desktopToggle.click();
      await page.waitForFunction(() => document.querySelector('.sidebar-default')?.classList.contains('sidebar-mini'));
      await page.waitForTimeout(450);
      const collapsedTransform = await desktopToggle.locator('.icon').evaluate((element) => getComputedStyle(element).transform);
      if (expandedTransform === collapsedTransform) issues.push('desktopSidebarArrow');
      await desktopToggle.click();
      await page.waitForFunction(() => !document.querySelector('.sidebar-default')?.classList.contains('sidebar-mini'));
      await page.waitForTimeout(450);
    }
  }

  return issues.map((type) => ({ type, route, width, theme, ...evidence }));
}

const browser = await chromium.launch({
  headless: true,
  executablePath: process.env.PSM_BROWSER_EXECUTABLE || undefined,
});
const page = await browser.newPage({
  viewport: { width: 1366, height: 900 },
  serviceWorkers: 'block',
});
if (process.env.PSM_APP_SHELL_OVERRIDE) {
  const appShell = await readFile(process.env.PSM_APP_SHELL_OVERRIDE, 'utf8');
  await page.route('**/src/templates/default/static/js/app-shell.js*', (route) => route.fulfill({
    status: 200,
    contentType: 'application/javascript; charset=utf-8',
    body: appShell,
  }));
}
const failures = [];
let auditedCases = 0;
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
          const isContainedOverflow = (element) => {
            for (let ancestor = element.parentElement; ancestor && ancestor !== document.body; ancestor = ancestor.parentElement) {
              const ancestorStyle = getComputedStyle(ancestor);
              if (
                ancestorStyle.position === 'fixed'
                || ['auto', 'clip', 'hidden', 'scroll'].includes(ancestorStyle.overflowX)
              ) { return true; }
            }
            return false;
          };
          const overflows = [...document.querySelectorAll('body *')].map((element) => {
            const style = getComputedStyle(element);
            if (
              style.display === 'none' || style.visibility === 'hidden' || style.position === 'fixed'
              || element.closest('.sidebar-mini.overflow-hidden, .offcanvas:not(.show)') || isContainedOverflow(element)
            ) { return null; }
            const box = element.getBoundingClientRect();
            if (Number(style.opacity) === 0 || box.width === 0 || box.height === 0) { return null; }
            return { selector: element.id || String(element.className || element.tagName), left: box.left, right: box.right };
          }).filter((item) => item && (item.left < -1 || item.right > viewport + 1)).slice(0, 20);
          return { viewport, scrollWidth: document.documentElement.scrollWidth, overflows, title: document.title };
        });
        if (result.scrollWidth > result.viewport || result.overflows.length || /error|fatal/i.test(result.title)) {
          failures.push({ route, width, theme: expectedTheme, ...result });
        }
        failures.push(...await auditNavigationChrome(page, route, width, expectedTheme));
        auditedCases += 1;
        if (process.env.PSM_AUDIT_VERBOSE === '1') {
          console.log(JSON.stringify({ route, width, theme: expectedTheme, ...result }));
        }
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
console.log(JSON.stringify({ status: 'ok', auditedCases, widths: widths.length, routes: routes.length, themes: themes.length }));
