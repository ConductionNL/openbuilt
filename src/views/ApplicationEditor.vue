<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!--
  - ApplicationEditor — two-tab shell wrapping the visual PageDesigner
  - (Design tab, default) and the Raw JSON textarea fallback. Both tabs
  - share the in-flight manifest state via the applicationEditor Pinia
  - store, so unsaved edits survive a tab switch. Implements
  - MODIFIED REQ-OBR-005 (Default tab is Design, Unsaved edits survive a
  - tab switch, Invalid edit is blocked before save) and task 5.2.
  -->
<template>
	<div class="application-editor">
		<header class="application-editor__header">
			<h2>{{ t('openbuilt', 'Application editor') }}</h2>
			<div v-if="store.loading" class="application-editor__status">
				{{ t('openbuilt', 'Loading…') }}
			</div>
			<div v-if="store.loadError" class="application-editor__error" role="alert">
				{{ store.loadError }}
			</div>
			<div class="application-editor__toolbar">
				<button
					type="button"
					class="application-editor__tab"
					:class="{ 'application-editor__tab--active': activeTab === 'design' }"
					@click="switchTab('design')">
					{{ t('openbuilt', 'Design') }}
				</button>
				<button
					type="button"
					class="application-editor__tab"
					:class="{ 'application-editor__tab--active': activeTab === 'json' }"
					@click="switchTab('json')">
					{{ t('openbuilt', 'Raw JSON') }}
				</button>
				<span class="application-editor__spacer" />
				<span v-if="store.dirty" class="application-editor__dirty">
					{{ t('openbuilt', 'Unsaved changes') }}
				</span>
				<button
					type="button"
					class="application-editor__save"
					:disabled="!canSave"
					@click="onSave">
					{{ store.saving ? t('openbuilt', 'Saving…') : t('openbuilt', 'Save') }}
				</button>
			</div>
			<div v-if="store.saveError" class="application-editor__error" role="alert">
				{{ store.saveError }}
			</div>
		</header>

		<section v-show="activeTab === 'design'" class="application-editor__pane">
			<PageDesigner
				v-if="store.manifest"
				:manifest="store.manifest"
				:slug="store.slug"
				@update:manifest="onManifestUpdate"
				@save-and-preview="onSaveAndPreview" />
			<p v-else class="application-editor__empty">
				{{ t('openbuilt', 'Load an application to start editing.') }}
			</p>
		</section>

		<section v-show="activeTab === 'json'" class="application-editor__pane">
			<p class="application-editor__hint">
				{{ t('openbuilt', 'Raw JSON tab — integrator fallback. Edits round-trip into the Design tab on commit.') }}
			</p>
			<textarea
				class="application-editor__textarea"
				spellcheck="false"
				:value="store.rawJsonDraft"
				@input="onRawJsonInput($event.target.value)" />
			<p v-if="rawJsonError" class="application-editor__error" role="alert">
				{{ rawJsonError }}
			</p>
		</section>
	</div>
</template>

<script>
import { useApplicationEditorStore } from '../store/modules/applicationEditor.js'
import PageDesigner from './PageDesigner.vue'

export default {
	name: 'ApplicationEditor',
	components: { PageDesigner },
	props: {
		// Optional UUID — when set, the editor auto-loads on mount.
		uuid: {
			type: String,
			default: '',
		},
		// Optional slug — used for the live-preview deep-link fallback.
		slug: {
			type: String,
			default: '',
		},
		// Initial tab; design is the default per REQ-OBR-005.
		initialTab: {
			type: String,
			default: 'design',
			validator: (v) => ['design', 'json'].includes(v),
		},
	},
	setup() {
		const store = useApplicationEditorStore()
		return { store }
	},
	data() {
		return {
			activeTab: this.initialTab,
			rawJsonError: '',
		}
	},
	computed: {
		canSave() {
			return this.store.dirty && !this.store.saving && !this.rawJsonError
		},
	},
	watch: {
		uuid: {
			immediate: true,
			handler(val) {
				if (val) {
					this.store.load(val)
				}
			},
		},
	},
	methods: {
		switchTab(tab) {
			// Unsaved edits survive a tab switch — the store is the single
			// source of truth so this is a pure UI toggle.
			this.activeTab = tab
		},
		onManifestUpdate(manifest) {
			this.store.manifest = manifest
			this.store.touch()
		},
		onRawJsonInput(text) {
			const result = this.store.commitRawJsonDraft(text)
			this.rawJsonError = result.ok ? '' : result.error
		},
		async onSave() {
			const ok = await this.store.save()
			if (ok) {
				this.rawJsonError = ''
			}
		},
		onSaveAndPreview() {
			// Save then open the spec-1 built-app deep link in a new tab.
			this.store.save().then((ok) => {
				if (ok && this.store.slug) {
					const url = `/index.php/apps/openbuilt/builder/${this.store.slug}`
					window.open(url, '_blank', 'noopener')
				}
			})
		},
	},
}
</script>

<style scoped>
.application-editor {
	padding: 8px 4px 24px;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.application-editor__header {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.application-editor__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.application-editor__status {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.application-editor__error {
	margin: 0;
	font-size: 13px;
	color: var(--color-error);
	padding: 4px 8px;
	background: var(--color-background-hover);
	border-left: 3px solid var(--color-error);
	border-radius: var(--border-radius);
}

.application-editor__toolbar {
	display: flex;
	align-items: center;
	gap: 6px;
}

.application-editor__spacer {
	flex: 1;
}

.application-editor__tab {
	padding: 6px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
	font-size: 13px;
}

.application-editor__tab--active {
	background: var(--color-primary-element-light);
	border-color: var(--color-primary-element);
	font-weight: 600;
}

.application-editor__dirty {
	font-size: 12px;
	color: var(--color-warning, var(--color-text-maxcontrast));
	font-style: italic;
}

.application-editor__save {
	padding: 6px 14px;
	border: 1px solid var(--color-primary-element);
	border-radius: var(--border-radius);
	background: var(--color-primary-element);
	color: var(--color-primary-text);
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
}

.application-editor__save[disabled] {
	cursor: not-allowed;
	opacity: 0.6;
}

.application-editor__pane {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.application-editor__hint {
	margin: 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.application-editor__textarea {
	min-height: 60vh;
	font-family: monospace;
	font-size: 13px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.application-editor__empty {
	padding: 16px;
	color: var(--color-text-maxcontrast);
}
</style>
