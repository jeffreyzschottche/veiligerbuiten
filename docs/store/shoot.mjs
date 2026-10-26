import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';

const OUT = process.argv[2];
const only = process.argv[3]; // optioneel: 'test'
const token = fs.readFileSync(new URL('./token.txt', import.meta.url), 'utf8').trim();
const BASE = 'http://localhost:3000';
const ME = { latitude: 52.3676, longitude: 4.8897 };

// iPhone 6.9" (1320x2868) en Android-telefoon (1080x1920, 9:16).
const devices = only === 'test'
  ? [{ id: 'iphone', width: 440, height: 956, scale: 3 }]
  : process.env.SHOT_DEVICE === 'iphone63'
    ? [{ id: 'iphone63', width: 402, height: 874, scale: 3 }]
    : [{ id: 'iphone', width: 440, height: 956, scale: 3 }, { id: 'android', width: 360, height: 640, scale: 3 }];
const locales = only === 'test' ? ['nl'] : [process.env.SHOT_LOCALE || 'nl'];

const hideDevtools = `#nuxt-devtools-container, nuxt-devtools-frame, [data-v-inspector], .nuxt-devtools { display: none !important; }`;

const browser = await chromium.launch({ channel: 'chrome', headless: true });
for (const device of devices) {
  for (const locale of locales) {
    const context = await browser.newContext({
      viewport: { width: device.width, height: device.height },
      deviceScaleFactor: device.scale,
      isMobile: true,
      hasTouch: true,
      locale: locale === 'nl' ? 'nl-NL' : 'en-GB',
      geolocation: ME,
      permissions: ['geolocation'],
    });
    await context.addCookies([{ name: 'vb_locale', value: locale, url: BASE }]);
    await context.addInitScript(([t]) => {
      localStorage.setItem('vb_auth_token', JSON.stringify(t));
      localStorage.removeItem('vb_account');
    }, [token]);
    // Alleen demo- en officiële meldingen in de screenshots; gescrapete P2000-sites niet.
    await context.route('**/api/v1/incidents/sync**', async (route) => {
      const response = await route.fetch();
      const body = await response.json().catch(() => null);
      if (body && Array.isArray(body.data)) body.data = body.data.filter((item) => ['user', 'p2000', 'ndw', 'luchtmeetnet'].includes(item.source));
      if (body && Array.isArray(body.changes)) body.changes = body.changes.filter((item) => ['user', 'p2000', 'ndw', 'luchtmeetnet'].includes(item.source));
      await route.fulfill({ response, json: body });
    });
    const page = await context.newPage();
    const dir = path.join(OUT, `${device.id}-${locale}`);
    fs.mkdirSync(dir, { recursive: true });
    const clean = () => page.evaluate(() => document.querySelectorAll('#nuxt-devtools-container, nuxt-devtools-frame').forEach((el) => el.remove()));
    page.on('load', () => { clean().catch(() => {}); });
    const tap = async (selector, index = 0) => { await clean(); await page.locator(selector).nth(index).click({ force: true }); };
    const shot = async (name) => {
      await clean();
      await page.addStyleTag({ content: hideDevtools });
      await page.waitForTimeout(600);
      await page.screenshot({ path: path.join(dir, `${name}.png`) });
      console.log(`${device.id}-${locale}/${name}.png`);
    };

    // 1. Kaart met paneel "In je buurt"
    await page.goto(`${BASE}/app`, { waitUntil: 'networkidle' });
    await page.waitForSelector('.maplibregl-canvas', { timeout: 20000 });
    await page.waitForTimeout(4500);
    await shot('1-kaart');
    if (only === 'test') break;

    // 2. Panel open
    await clean(); await page.locator('.nearby').first().click({ position: { x: 60, y: 20 }, force: true }).catch(() => {});
    await page.waitForTimeout(1200);
    await shot('2-in-je-buurt');

    // 3. Incident (intimidatie) geopend via deeplink
    await page.goto(`${BASE}/app?incident=230`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(5000);
    await shot('3-melding');

    // 4. Melden
    await page.goto(`${BASE}/app`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(4000);
    await tap('.report-fab');
    await page.waitForTimeout(1500);
    await shot('4-melden');

    // 5. Plekken
    await page.keyboard.press('Escape').catch(() => {});
    await page.goto(`${BASE}/app`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3500);
    await tap('.tabbar__tab', 1);
    await page.waitForTimeout(1500);
    await shot('5-plekken');

    // 6. Account / meldingsinstellingen
    await tap('.tabbar__tab', 2);
    await page.waitForTimeout(1500);
    await shot('6-instellingen');

    await context.close();
  }
}
await browser.close();
