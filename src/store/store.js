// SPDX-License-Identifier: EUPL-1.2
/**
 * Store bootstrap. Wires the shared @conduction/nextcloud-vue object store
 * and registers the OpenBuilt-specific object types (currently: `application`).
 *
 * The page-editor consumes `application` objects from the shared `openbuilt`
 * register; the per-app register `openbuilt-{slug}` is reached via the same
 * createObjectStore using a different type slug when the page-editor needs
 * to enumerate per-app schemas (see useRegisterPicker composable).
 */
import { generateUrl } from '@nextcloud/router'
import { useObjectStore } from './modules/object.js'
import { useSettingsStore } from './modules/settings.js'

export async function initializeStores() {
	const settingsStore = useSettingsStore()
	const objectStore = useObjectStore()

	objectStore.configure({
		baseUrl: generateUrl('/apps/openregister/api/objects'),
	})

	// Register the shared `openbuilt/application` type — see hybrid register
	// model in design.md: page editor consumes Application records from the
	// shared `openbuilt` register; the manifest it produces references
	// schemas in the per-app register `openbuilt-{slug}`.
	objectStore.registerObjectType('application', 'application', 'openbuilt')

	await settingsStore.fetchSettings()

	return { settingsStore, objectStore }
}

export { useObjectStore, useSettingsStore }
