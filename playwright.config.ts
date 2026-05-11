/*
 * SPDX-FileCopyrightText: 2026 OpenBuilt Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright runner configuration for the OpenBuilt end-to-end suite.
 *
 * The suite assumes:
 *   - A reachable Nextcloud instance at NC_BASE_URL (default
 *     http://localhost:8080) with the openbuilt app enabled.
 *   - Admin credentials NC_ADMIN_USER / NC_ADMIN_PASS (defaults
 *     admin / admin) usable to log in via /index.php/login.
 *   - The hello-world seed Application is present (created by the
 *     `SeedHelloWorld` repair step on install).
 *
 * Auth strategy: each spec drives the Nextcloud login form once via
 * `page.goto('/index.php/login')` + fill + submit. A storage-state cache
 * would shave a few seconds but the openbuilt CI matrix is single-spec
 * so the cost is negligible and the spec stays self-contained.
 *
 * `httpCredentials` + the `OCS-APIRequest` header here let test specs
 * call the OR REST and openbuilt manifest endpoints directly via
 * `request.fetch(...)` without re-logging-in through the browser.
 *
 * One-time setup (NOT run automatically — it downloads ~150 MB of
 * Chromium binaries):
 *
 *     npm run test:e2e:install
 *
 * After that, run the suite with:
 *
 *     npm run test:e2e
 */

import { defineConfig, devices } from '@playwright/test'

const baseURL = process.env.NC_BASE_URL ?? 'http://localhost:8080'
const adminUser = process.env.NC_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NC_ADMIN_PASS ?? 'admin'

export default defineConfig({
	testDir: './tests/e2e',
	timeout: 60_000,
	expect: {
		timeout: 10_000,
	},
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
	use: {
		baseURL,
		httpCredentials: {
			username: adminUser,
			password: adminPassword,
		},
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
		},
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		actionTimeout: 10_000,
		navigationTimeout: 30_000,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
})
