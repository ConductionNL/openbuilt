/**
 * SPDX-FileCopyrightText: 2026 OpenBuilt Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest configuration for OpenBuilt Vue 2 unit tests.
 *
 * Test files live under `tests/composables/**` and `tests/components/**`
 * and `tests/views/**` and run in a jsdom environment so DOM assertions
 * (`wrapper.find`, `wrapper.text()`) work without launching a browser.
 *
 * The Nextcloud `t()` / `n()` translation helpers are stubbed in
 * `tests/vitest/setup.js`.
 *
 * This file mirrors the schema-editor / mydash setup intentionally — the
 * same CSS-noop plugin, the same inline-deps list, the same conduction
 * stub alias. Coordinate any changes here with the matching schema-editor
 * spec to keep both apps in sync.
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
		include: [
			'tests/composables/**/*.spec.{js,ts}',
			'tests/components/**/*.spec.{js,ts}',
			'tests/views/**/*.spec.{js,ts}',
		],
		exclude: [
			'tests/e2e/**',
			'node_modules/**',
		],
		setupFiles: [path.resolve(__dirname, 'tests/vitest/vitest-setup.js')],
		server: {
			deps: {
				inline: [
					/@nextcloud\/vue/,
					/@nextcloud\/axios/,
					/@nextcloud\/router/,
					/@nextcloud\/auth/,
					/@nextcloud\/dialogs/,
					/@conduction\/nextcloud-vue/,
					/vue-material-design-icons/,
					/vue-select/,
					/vue-multiselect/,
					/vuedraggable/,
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
			// vuedraggable is mocked per-test where re-order semantics
			// matter; the default alias is a passthrough stub so other
			// component mounts that incidentally import Draggable do not
			// crash.
			{ find: /^vuedraggable$/, replacement: path.resolve(__dirname, 'tests/vitest/stubs/vuedraggable.js') },
			// @nextcloud/router and @nextcloud/auth call into runtime
			// globals (OC.config, csrf-token <head> meta) that don't
			// exist in jsdom. Aliased stubs return deterministic strings.
			{ find: /^@nextcloud\/router$/, replacement: path.resolve(__dirname, 'tests/vitest/stubs/nextcloud-router.js') },
			{ find: /^@nextcloud\/auth$/, replacement: path.resolve(__dirname, 'tests/vitest/stubs/nextcloud-auth.js') },
		],
	},
}
