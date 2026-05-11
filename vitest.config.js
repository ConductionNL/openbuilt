/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest configuration for OpenBuilt Vue 2 unit tests.
 *
 * Test files live under `tests/views/*.spec.js` and `tests/modals/*.spec.js`
 * and run in a jsdom environment so DOM assertions (`wrapper.find`,
 * `wrapper.text()`, inline-style inspection) work without launching a
 * browser.
 *
 * The Nextcloud `t()` translation helper is stubbed in `tests/vitest/setup.js`
 * via a global mixin (so it is available on `this` in components and on
 * `globalThis` for script-level calls).
 */

const path = require('path')
const vue2 = require('@vitejs/plugin-vue2')

/**
 * Side-effect imports of `*.css` from `@nextcloud/vue` (and friends) crash
 * Vite's transform pipeline because those CSS files don't exist on disk —
 * they are produced by a parallel vite build and referenced via tree-shaken
 * `import './foo.css'` lines that survive transpilation. A small plugin
 * intercepts `*.css` resolution and returns a virtual empty module so unit
 * tests can mount components without ever loading a stylesheet.
 */
const cssNoop = {
	name: 'openbuilt-css-noop',
	enforce: 'pre',
	resolveId(id) {
		if (typeof id === 'string' && /\.css(\?.*)?$/.test(id)) {
			return '\0virtual:css-noop'
		}
		return null
	},
	load(id) {
		if (id === '\0virtual:css-noop') {
			return 'export default {}'
		}
		return null
	},
}

module.exports = {
	plugins: [
		cssNoop,
		vue2.default ? vue2.default() : vue2(),
	],
	test: {
		environment: 'jsdom',
		globals: false,
		include: ['tests/**/*.spec.{js,ts}'],
		exclude: ['tests/e2e/**', 'tests/integration/**', 'tests/Unit/**', 'tests/unit/**', 'node_modules/**'],
		setupFiles: [path.resolve(__dirname, 'tests/vitest/setup.js')],
		server: {
			deps: {
				inline: [
					/@nextcloud\/vue/,
					/@nextcloud\/axios/,
					/@conduction\/nextcloud-vue/,
					/@nextcloud\/dialogs/,
					/vue-material-design-icons/,
					/vue-select/,
					/vue-multiselect/,
					/vue2-datepicker/,
					/floating-vue/,
				],
			},
		},
	},
	resolve: {
		alias: [
			{ find: '@', replacement: path.resolve(__dirname, 'src') },
			// `@conduction/nextcloud-vue` ships a CJS bundle that
			// `require()`s `.vue` files which Vite's transform pipeline
			// cannot consume. Tests that need the actual component
			// behaviour use `vi.mock(...)`; everyone else gets a tiny
			// stub so transitive imports don't crash.
			{ find: /^@conduction\/nextcloud-vue$/, replacement: path.resolve(__dirname, 'tests/vitest/stubs/conduction-nextcloud-vue.js') },
			{ find: /^@nextcloud\/vue$/, replacement: path.resolve(__dirname, 'tests/vitest/stubs/nextcloud-vue.js') },
		],
	},
}
