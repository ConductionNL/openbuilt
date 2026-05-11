/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest stub for `@conduction/nextcloud-vue`.
 *
 * The published package ships a CJS bundle that does `require('foo.vue')`
 * which Vite cannot transform under the unit-test pipeline (vue-loader is
 * a webpack plugin; the @vitejs/plugin-vue2 transform is gated on the
 * Vite resolver, not Node's `require`). Tests that mount these components
 * do not exercise their rendered markup — they only need the symbol to be
 * a valid Vue component object — so we substitute lightweight stubs at
 * the alias layer.
 */

const stub = (name) => ({ name, render: (h) => h('div') })

export const NcModal = stub('NcModal')
export const NcButton = stub('NcButton')
export const NcTextField = stub('NcTextField')
export const NcSelect = stub('NcSelect')
export const NcEmptyContent = stub('NcEmptyContent')
export const NcLoadingIcon = stub('NcLoadingIcon')

export default {
	NcModal,
	NcButton,
	NcTextField,
	NcSelect,
	NcEmptyContent,
	NcLoadingIcon,
}
