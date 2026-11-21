import { test, expect } from '@playwright/test';

/** REQ-DEMO-013: presets fill the editor and the export answers a .docx download; the custom-config mode toggle works. */
test.describe('HtmlToWord demo', () => {
  test('a preset fills the HTML field and the export downloads a .docx', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByTestId('demo-platform')).toBeVisible();
    const html = page.locator('textarea[name="html"]');
    const before = await html.inputValue();
    await page.locator('[data-load-preset]').nth(1).click();
    await expect(html).not.toHaveValue(before);

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Download .docx' }).click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/\.docx$/);
  });

  test('inline mode hides the YAML profile row', async ({ page }) => {
    await page.goto('/custom-config');
    const row = page.locator('#profile-row');
    await expect(row).toBeVisible();
    await page.locator('input[name="mode"][value="inline"]').check();
    await expect(row).toBeHidden();
    await page.locator('input[name="mode"][value="merge"]').check();
    await expect(row).toBeVisible();
  });
});
