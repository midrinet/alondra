import { test, expect } from '../fixtures/test';
import { request as playwrightRequest } from '@playwright/test';
import AlondraHelper from '../fixtures/AlondraHelper';
import WpAdmin from '../fixtures/WpAdmin';

const ALONDRA_PLUGIN = 'alondra/alondra.php';
const ALONDRA_COPY = 'alondra-uninstall-copy/alondra.php';

/**
 * Drive the real wp-admin Delete flow against the disposable plugin copy so
 * uninstall.php runs, without ever deleting the bind-mounted source.
 */
async function uninstallViaCopy(wpAdmin, helper) {
  // The recursive filesystem copy and the alondra deactivation (DB-only) hit
  // disjoint resources, so overlap them.
  await Promise.all([
    helper.provisionPluginCopy(),
    wpAdmin.deactivatePlugin(ALONDRA_PLUGIN),
  ]);
  await wpAdmin.activatePlugin(ALONDRA_COPY);
  await wpAdmin.deactivatePlugin(ALONDRA_COPY);
  await wpAdmin.deletePlugin(ALONDRA_COPY);
}

/**
 * Lifecycle tests drive the real plugins.php screen and toggle the live
 * plugin's active state, so they run serially and restore alondra to active
 * with its data intact afterwards. Ordered deactivate-then-activate so each
 * test starts from the state the previous one left.
 */
test.describe.serial('Activation/deactivation lifecycle', () => {

  let apiCtx;
  let helper;

  test.beforeAll(async ({ baseURL }) => {
    apiCtx = await playwrightRequest.newContext({ baseURL });
    helper = new AlondraHelper(apiCtx, expect);
    // Establish the "starts active" precondition rather than relying on
    // ambient state left by other specs or a prior aborted run.
    await helper.ensureAlondraActive();
    await helper.clearData();
    await helper.createSampleTieredPricing();
  });

  test.afterAll(async () => {
    // Safety net: guarantee alondra is active for the specs that follow,
    // even if a test above failed mid-flow. Page-free so it can't be tripped
    // up by a torn-down browser during teardown.
    await helper.ensureAlondraActive();
    await helper.clearData();
    await apiCtx.dispose();
  });

  // Deactivation must leave both the schema and the pricing data alone.
  test('Validate deactivate plugin', async ({ page, baseURL }) => {
    const wpAdmin = new WpAdmin(page, baseURL, expect);

    expect(await wpAdmin.isPluginActive(ALONDRA_PLUGIN), 'plugin starts active').toBe(true);

    await wpAdmin.deactivatePlugin(ALONDRA_PLUGIN);
    expect(await wpAdmin.isPluginActive(ALONDRA_PLUGIN), 'plugin is inactive after deactivate').toBe(false);

    const status = await helper.getPluginStatus();
    expect(status.tables_exist, 'tables survive deactivation').toBe(true);
    console.log('✅ Deactivate: plugin inactive, data preserved');
  });

  // Activation (re)creates the custom tables via the idempotent setup.
  test('Validate activate plugin', async ({ page, baseURL }) => {
    const wpAdmin = new WpAdmin(page, baseURL, expect);

    expect(await wpAdmin.isPluginActive(ALONDRA_PLUGIN), 'plugin starts inactive').toBe(false);

    await wpAdmin.activatePlugin(ALONDRA_PLUGIN);
    expect(await wpAdmin.isPluginActive(ALONDRA_PLUGIN), 'plugin is active after activate').toBe(true);

    const status = await helper.getPluginStatus();
    expect(status.tables_exist, 'tables present after activation').toBe(true);
    console.log('✅ Activate: plugin active, tables present');
  });
});

/**
 * Uninstalling deletes the plugin options and nothing else: the pricing tables
 * and their rows stay so a reinstall finds the data intact. The flow deletes a
 * disposable copy of the plugin (isolated in the plugins volume, never the
 * bind-mounted source) through the real wp-admin Delete flow.
 */
test.describe.serial('Uninstall', () => {

  let apiCtx;
  let helper;

  test.beforeAll(async ({ baseURL }) => {
    apiCtx = await playwrightRequest.newContext({ baseURL });
    helper = new AlondraHelper(apiCtx, expect);
  });

  test.beforeEach(async () => {
    await helper.removePluginCopy();
    await helper.ensureAlondraActive();
    // Seed real pricing rows so the assertions cover actual data, not just
    // empty schema.
    await helper.clearData();
    await helper.createSampleTieredPricing();
  });

  test.afterAll(async () => {
    await helper.removePluginCopy();
    await helper.ensureAlondraActive();
    await helper.clearData();
    await apiCtx.dispose();
  });

  test('Uninstall deletes the options and keeps the pricing data', async ({ page, baseURL }) => {
    const wpAdmin = new WpAdmin(page, baseURL, expect);

    await uninstallViaCopy(wpAdmin, helper);

    const status = await helper.getPluginStatus();
    expect(status.tables_exist, 'all custom tables kept on uninstall').toBe(true);
    expect(status.rows, 'seeded pricing rows survive uninstall').toBeGreaterThan(0);
    expect(status.option_exists, 'settings option removed on uninstall').toBe(false);
    expect(status.version_option_exists, 'version option removed on uninstall').toBe(false);
    console.log('✅ Uninstall: options removed, pricing data preserved');
  });
});
