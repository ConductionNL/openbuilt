/*
 * SPDX-FileCopyrightText: 2026 OpenBuilt Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Global setup for Vitest unit tests. Stubs the Nextcloud `t()` and `n()`
 * translation helpers so component renders that call them resolve to the
 * bare key string. Loaded automatically via `test.setupFiles` in
 * `vitest.config.js`.
 *
 * Vue 2's template compiler emits `_vm.t(...)` (looked up on the component
 * instance), while plain script code calls `t(...)` (looked up on the
 * global). We register both shapes — a global mixin AND globalThis — so
 * every code path resolves.
 */

import Vue from 'vue'

const tStub = (_app, key, _vars) => key
const nStub = (_app, singular, plural, count) => (count === 1 ? singular : plural)

globalThis.t = tStub
globalThis.n = nStub

Vue.mixin({
	methods: {
		t: tStub,
		n: nStub,
	},
})

// Silence Vue 2 "production tip" + the recurring warning emitted when a
// component is mounted without a Pinia/store plug-in. We are unit-testing
// components in isolation; cluttering the test output with these is
// counter-productive.
Vue.config.productionTip = false
Vue.config.devtools = false
