/*
 * SPDX-FileCopyrightText: 2026 OpenBuilt Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest stub for `@conduction/nextcloud-vue`.
 *
 * The published package ships a CJS bundle that does `require('foo.vue')`
 * which Vite cannot transform under the unit-test pipeline. Tests that
 * mount these components don't exercise their rendered markup — they only
 * need the symbol to be a valid Vue component object — so we substitute
 * lightweight stubs at the alias layer.
 *
 * Two named exports drive the page-editor specs specifically:
 *
 *  - `validateManifest(manifest)` — returns `{ valid, errors }`. The
 *    default implementation reports no errors; tests that need errors
 *    swap this out via `vi.mock(...)`.
 *  - `useAppManifest(...)` — arity-1 by default (legacy shape); tests
 *    that simulate chain spec #2 swap this out via `vi.mock(...)` for
 *    an arity-2 function.
 *
 * Real visual coverage of `@conduction/nextcloud-vue` lives in the
 * upstream package's own test suite.
 */

const stub = (name) => ({ name, render: (h) => h('div') })

export const NcModal = stub('NcModal')
export const NcButton = stub('NcButton')
export const NcTextField = stub('NcTextField')
export const NcSelect = stub('NcSelect')
export const NcEmptyContent = stub('NcEmptyContent')
export const NcAppNavigation = stub('NcAppNavigation')
export const NcAppContent = stub('NcAppContent')
export const NcContent = stub('NcContent')
export const NcDashboardWidget = stub('NcDashboardWidget')
export const NcCheckboxRadioSwitch = stub('NcCheckboxRadioSwitch')

// Default validator: passes everything. Tests override this with vi.mock.
export const validateManifest = (_manifest) => ({ valid: true, errors: [] })

// Legacy arity-1 `useAppManifest`. Chain spec #2 ships an arity-2
// overload (appId, manifestObject). The arity is the discriminator used
// by `useLivePreview.js` — keep arity === 1 here so the default
// behaviour exercises the "preview unavailable" path.
export const useAppManifest = function (_appId) {
	return { manifest: null, loading: false }
}

export const createObjectStore = () => ({
	state: { results: [], selected: null },
	actions: {},
})

export default {
	NcModal,
	NcButton,
	NcTextField,
	NcSelect,
	NcEmptyContent,
	NcAppNavigation,
	NcAppContent,
	NcContent,
	NcDashboardWidget,
	NcCheckboxRadioSwitch,
	validateManifest,
	useAppManifest,
	createObjectStore,
}
