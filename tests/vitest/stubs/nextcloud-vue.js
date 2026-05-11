/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest stub for `@nextcloud/vue`.
 *
 * Same rationale as the @conduction/nextcloud-vue stub: the published
 * package re-exports many components via `.vue` SFC files that Vite's
 * unit-test transform cannot consume out of the box, so substitute
 * lightweight stubs at the alias layer.
 */

import { defineComponent, h } from 'vue'

const passThrough = (name) => defineComponent({
	name,
	props: {
		value: { type: [String, Number, Object], default: null },
		modelValue: { type: [String, Number, Object], default: null },
		label: { type: String, default: '' },
		inputLabel: { type: String, default: '' },
		placeholder: { type: String, default: '' },
		options: { type: Array, default: () => [] },
		open: { type: Boolean, default: false },
		size: { type: String, default: 'normal' },
		clearable: { type: Boolean, default: false },
		type: { type: String, default: 'secondary' },
		disabled: { type: Boolean, default: false },
		name: { type: String, default: '' },
	},
	emits: ['click', 'close', 'update:value', 'update:modelValue', 'input'],
	render() {
		return h(
			'div',
			{
				attrs: { 'data-stub': name, role: 'group' },
				on: {
					click: (e) => this.$emit('click', e),
				},
			},
			this.$slots.default,
		)
	},
})

export const NcModal = passThrough('NcModal')
export const NcButton = passThrough('NcButton')
export const NcTextField = passThrough('NcTextField')
export const NcSelect = passThrough('NcSelect')
export const NcEmptyContent = passThrough('NcEmptyContent')
export const NcLoadingIcon = passThrough('NcLoadingIcon')

export default {
	NcModal,
	NcButton,
	NcTextField,
	NcSelect,
	NcEmptyContent,
	NcLoadingIcon,
}
