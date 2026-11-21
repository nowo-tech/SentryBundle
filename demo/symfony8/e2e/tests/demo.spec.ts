import { test, expect } from '@playwright/test';

/** REQ-DEMO-013: the demo pages render on the UiKit shell and a use case page reports its case. */
test.describe('Sentry demo', () => {
  test('home, use case index and a result page render without a console error', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error') {
        errors.push(message.text());
      }
    });
    page.on('pageerror', (error) => errors.push(error.message));

    for (const path of ['/', '/sentry', '/sentry/capture-message']) {
      const response = await page.goto(path);
      expect(response?.status(), path).toBe(200);
      await expect(page.getByTestId('demo-platform')).toBeVisible();
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    }
    await expect(page.getByTestId('demo-use-case')).toHaveText('captureMessage');
    await page.waitForLoadState('networkidle');
    expect(errors).toEqual([]);
  });

  test('the index lists every use case as a table row', async ({ page }) => {
    await page.goto('/sentry');
    await expect(page.getByTestId('demo-case')).toHaveCount(12);
    await page.locator('a[href$="/sentry/capture-message"]').click();
    await expect(page).toHaveURL(/\/sentry\/capture-message$/);
  });
});
