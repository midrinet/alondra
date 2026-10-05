import { test, expect } from '../fixtures/test';

const RENDERED = [
  'alondra[layout_pos]',
  'alondra[clickable_layout]',
  'alondra[highlight_pricing]',
  'alondra[overwrite_product_price]',
  'alondra[enable_cache]',
  'alondra[uninstall_cleanup]',
];

const CHANGELOG_URL = 'https://alondra.midri.net/changelog';
const SUPPORT_URL = 'https://alondra.midri.net/support/';
const REVIEWS_URL = 'https://wordpress.org/support/plugin/alondra/reviews/';
const KOFI_URL = 'https://ko-fi.com/midrinet';

/**
 * The add-on product a "Get Alondra Plus" link buys: free's hidden pricing page with checkout=true once the site opted
 * in or skipped, Freemius's hosted checkout of the add-on before that.
 */
function checkoutProduct(href) {
  const url = new URL(href);
  if ('https://checkout.freemius.com' === url.origin) {
    return url.pathname.match(/^\/plugin\/(\d+)\//)?.[1];
  }
  expect(url.pathname).toMatch(/\/wp-admin\/admin\.php$/);
  expect(url.searchParams.get('page')).toBe('alondra-pricing');
  expect(url.searchParams.get('checkout')).toBe('true');
  return url.searchParams.get('plugin_id');
}

// Settings free does not ship: no control, placeholder or hidden input may carry them.
const UNRENDERED = [
  'pricing_layout',
  'color', 'bg_color', 'border_color',
  'highlight_color', 'highlight_bg_color', 'highlight_border_color',
  'disable_styles', 'live_product_price', 'live_total_price', 'strikethrough_price',
];

test.describe('Settings', () => {

  test('Renders only the free settings', async ({ settingsPage, page }) => {
    await settingsPage.goto();

    expect((await settingsPage.fieldNames()).sort()).toEqual([...RENDERED].sort());
    const html = await page.locator('form.alondra-settings').innerHTML();
    for (const key of UNRENDERED) {
      expect(html, `${key} must not be rendered`).not.toContain(key);
    }
    await expect(settingsPage.locator.field('layout_pos').locator('option')).toHaveCount(2);
  });

  test('A changed checkbox is saved', async ({ settingsPage, page }) => {
    await settingsPage.goto();
    const box = settingsPage.locator.field('uninstall_cleanup');
    const before = await box.isChecked();

    try {
      await box.setChecked(!before);
      await settingsPage.save();
      await page.reload();
      await expect(settingsPage.locator.field('uninstall_cleanup')).toBeChecked({ checked: !before });
    } finally {
      await settingsPage.goto();
      await settingsPage.locator.field('uninstall_cleanup').setChecked(before);
      await settingsPage.save();
    }
  });

  test('Shows one banner with the in-admin upgrade and no donation', async ({ settingsPage, tieredPricingListingPage, page }) => {
    await settingsPage.goto();

    await expect(settingsPage.banner()).toHaveCount(1);
    await expect(settingsPage.banner()).not.toHaveClass(/is-dismissible/);
    await expect(settingsPage.banner()).toContainText('Alondra Plus adds percentage prices');
    await expect(settingsPage.banner()).not.toContainText('Ko-fi');

    expect(checkoutProduct(await settingsPage.locator.upgradeLink().getAttribute('href'))).toBe('38115');
    await expect(settingsPage.locator.upgradeLink()).not.toHaveAttribute('target', '_blank');

    await tieredPricingListingPage.goto();
    await expect(settingsPage.banner()).toHaveCount(1);
    await expect(settingsPage.locator.upgradeLink()).toBeVisible();
  });

  test('Get Alondra Plus opens the add-on checkout', async ({ settingsPage, page }) => {
    await settingsPage.goto();
    const href = await settingsPage.locator.upgradeLink().getAttribute('href');

    // Once the site opted in or skipped, free's hidden pricing page hands over to Freemius's hosted checkout; before
    // that, the link is the hosted checkout itself. The page's own request context carries the admin cookies; the
    // checkout itself is never loaded.
    let checkout = new URL(href);
    if ('https://checkout.freemius.com' !== checkout.origin) {
      const response = await page.request.get(href, { maxRedirects: 0 });
      expect(response.status()).toBeGreaterThanOrEqual(300);
      expect(response.status()).toBeLessThan(400);
      checkout = new URL(response.headers()['location']);
    }
    expect(checkout.origin).toBe('https://checkout.freemius.com');
    expect(checkout.searchParams.get('plugin_id') ?? checkoutProduct(checkout.href)).toBe('38115');
    // The checkout's way back leads to the settings page, never to this plugin's own pricing page.
    const back = new URL(checkout.searchParams.get('cancel_url'));
    expect(back.pathname).toMatch(/\/wp-admin\/options-general\.php$/);
    expect(back.searchParams.get('page')).toBe('alondra-settings');
  });

  test('The plugin row offers Alondra Plus and free sells no plan of its own', async ({ settingsPage, page }) => {
    await settingsPage.wpAdmin.goToPlugins();
    const row = settingsPage.wpAdmin.pluginRow('alondra/alondra.php');

    expect(checkoutProduct(await row.getByRole('link', { name: 'Get Alondra Plus' }).getAttribute('href'))).toBe('38115');
    await expect(row.getByRole('link', { name: 'Upgrade', exact: true })).toHaveCount(0);
    await expect(page.locator('#adminmenu a[href*="page=alondra-pricing"]')).toHaveCount(0);
  });

  test('Shows the logo and the plain title in the header', async ({ settingsPage }) => {
    await settingsPage.goto();

    await expect(settingsPage.locator.logo()).toBeVisible();
    await expect(settingsPage.locator.logo()).toHaveAttribute('aria-hidden', 'true');
    await expect(settingsPage.locator.title()).toHaveText('Alondra');
    await expect(settingsPage.locator.titlebar().getByRole('link')).toHaveCount(0);
  });

  test('Shows the edition, the changelog, the rating and Ko-fi in the footer', async ({ settingsPage }) => {
    await settingsPage.goto();

    await expect(settingsPage.locator.edition()).toHaveText(/^FREE v\S+$/);
    const links = [
      [settingsPage.locator.changelogLink(), CHANGELOG_URL],
      [settingsPage.locator.supportLink(), SUPPORT_URL],
      [settingsPage.locator.reviewsLink(), REVIEWS_URL],
      [settingsPage.locator.donateLink(), KOFI_URL],
    ];
    for (const [link, href] of links) {
      await expect(link).toHaveAttribute('href', href);
      await expect(link).toHaveAttribute('target', '_blank');
      await expect(link).toHaveAttribute('rel', 'noopener');
    }
    await expect(settingsPage.locator.donateLink()).toHaveAccessibleName('Support Us on Ko-fi');
    await expect(settingsPage.locator.donateLink()).toHaveText('Support Us');
    await expect(settingsPage.locator.reviewsLink()).toHaveText('Rate us on WordPress.org');
  });

  test('The changelog, help and reviews links land on their pages', async ({ settingsPage, request }) => {
    await settingsPage.goto();

    for (const link of [settingsPage.locator.changelogLink(), settingsPage.locator.supportLink()]) {
      const response = await request.get(await link.getAttribute('href'));
      expect(response.status()).toBe(200);
      const url = new URL(response.url());
      expect(url.hostname).toBe('alondra.midri.net');
      expect(url.pathname.replace(/\/+$/, '')).not.toBe('');
    }

    const reviews = await request.get(await settingsPage.locator.reviewsLink().getAttribute('href'));
    expect(reviews.status()).toBe(200);
    const reviewsUrl = new URL(reviews.url());
    expect(reviewsUrl.hostname).toBe('wordpress.org');
    expect(reviewsUrl.pathname).toMatch(/^\/support\/plugin\/alondra\/reviews\b/);
  });

  test('Tiered Pricing shows the logo but neither Ko-fi, the rating nor help', async ({ settingsPage, tieredPricingListingPage, page }) => {
    await tieredPricingListingPage.goto();
    await expect(settingsPage.locator.logo()).toBeVisible();
    await expect(settingsPage.locator.donateLink()).toHaveCount(0);
    await expect(page.locator(`a[href="${KOFI_URL}"]`)).toHaveCount(0);
    await expect(page.locator(`a[href="${REVIEWS_URL}"]`)).toHaveCount(0);
    await expect(page.locator(`a[href="${SUPPORT_URL}"]`)).toHaveCount(0);

    await tieredPricingListingPage.addNew();
    await expect(page).toHaveURL(/[?&]action=add\b/);
    await expect(settingsPage.locator.logo()).toBeVisible();
    await expect(settingsPage.locator.donateLink()).toHaveCount(0);
    await expect(page.locator(`a[href="${KOFI_URL}"]`)).toHaveCount(0);
    await expect(page.locator(`a[href="${REVIEWS_URL}"]`)).toHaveCount(0);
    await expect(page.locator(`a[href="${SUPPORT_URL}"]`)).toHaveCount(0);
  });

  test('Header, tabs and footer fit a 360px screen', async ({ settingsPage, page }) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await settingsPage.goto();

    for (const locator of [settingsPage.locator.titlebar(), settingsPage.locator.tabs(), settingsPage.locator.footer()]) {
      await expect(locator).toBeVisible();
      const { scrollWidth, clientWidth } = await locator.evaluate(el => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth }));
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
      const box = await locator.boundingBox();
      expect(box.x + box.width).toBeLessThanOrEqual(360);
    }
  });

  test('The Tiered Pricing titlebar fits a 360px screen', async ({ settingsPage, tieredPricingListingPage, page }) => {
    await page.setViewportSize({ width: 360, height: 800 });
    const fits = async () => {
      const titlebar = settingsPage.locator.titlebar();
      await expect(titlebar).toBeVisible();
      const { scrollWidth, clientWidth } = await titlebar.evaluate(el => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth }));
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
      const box = await titlebar.boundingBox();
      expect(box.x + box.width).toBeLessThanOrEqual(360);
    };

    await tieredPricingListingPage.goto();
    await fits();
    await tieredPricingListingPage.addNew();
    await expect(page).toHaveURL(/[?&]action=add\b/);
    await fits();
  });

  test('Shows one Settings tab holding the form', async ({ settingsPage, page }) => {
    await settingsPage.goto();

    await expect(page.locator('.alondra-tab')).toHaveText(['Settings']);
    await expect(page.locator('.alondra-tab.alondra-active')).toHaveText('Settings');
    await expect(page.locator('#alondra-tab-settings.alondra-active form.alondra-settings')).toBeVisible();
  });

  test('Shows the saved notice once', async ({ settingsPage, page }) => {
    await settingsPage.goto();
    await settingsPage.save();

    await expect(page.getByText('Settings saved.')).toHaveCount(1);
  });
});
