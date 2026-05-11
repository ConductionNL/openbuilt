<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="openbuilt-dashboard">
		<header class="openbuilt-dashboard__header">
			<h2>{{ t('openbuilt', 'Dashboard') }}</h2>
			<p class="openbuilt-dashboard__lead">
				{{ t('openbuilt', 'Starter overview with sample KPIs and activity placeholders. Replace this view with your own data.') }}
			</p>
		</header>

		<CnKpiGrid :columns="4">
			<CnStatsBlock
				:title="t('openbuilt', 'Open items')"
				:count="12"
				:count-label="t('openbuilt', 'sample')"
				:icon="FolderOutline"
				variant="primary"
				horizontal />
			<CnStatsBlock
				:title="t('openbuilt', 'Due this week')"
				:count="5"
				:count-label="t('openbuilt', 'sample')"
				:icon="CalendarClock"
				variant="warning"
				horizontal />
			<CnStatsBlock
				:title="t('openbuilt', 'Completed')"
				:count="48"
				:count-label="t('openbuilt', 'sample')"
				:icon="CheckCircleOutline"
				variant="success"
				horizontal />
			<CnStatsBlock
				:title="t('openbuilt', 'Team members')"
				:count="7"
				:count-label="t('openbuilt', 'sample')"
				:icon="AccountGroupOutline"
				variant="default"
				horizontal />
		</CnKpiGrid>

		<div class="openbuilt-dashboard__columns">
			<CnConfigurationCard :title="t('openbuilt', 'Recent activity')">
				<ul class="openbuilt-dashboard__placeholder-list">
					<li>{{ t('openbuilt', 'Placeholder: user opened a record') }}</li>
					<li>{{ t('openbuilt', 'Placeholder: status changed to Review') }}</li>
					<li>{{ t('openbuilt', 'Placeholder: comment added') }}</li>
				</ul>
			</CnConfigurationCard>

			<CnConfigurationCard :title="t('openbuilt', 'Quick actions')">
				<p class="openbuilt-dashboard__hint">
					{{ t('openbuilt', 'Wire buttons here to create records, open lists, or deep links. Use the sidebar for Settings and Documentation.') }}
				</p>
				<NcButton type="primary" @click="$router.push({ name: 'TemplateGallery' })">
					{{ t('openbuilt', 'Create from template') }}
				</NcButton>
			</CnConfigurationCard>
		</div>
	</div>
</template>

<script>
import { CnConfigurationCard, CnKpiGrid, CnStatsBlock } from '@conduction/nextcloud-vue'
import { NcButton } from '@nextcloud/vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'

export default {
	name: 'Dashboard',
	components: {
		CnConfigurationCard,
		CnKpiGrid,
		CnStatsBlock,
		NcButton,
	},
	data() {
		return {
			FolderOutline,
			CalendarClock,
			CheckCircleOutline,
			AccountGroupOutline,
		}
	},
}
</script>

<style scoped>
.openbuilt-dashboard {
	padding: 8px 4px 24px;
	max-width: 1200px;
}

.openbuilt-dashboard__header {
	margin-bottom: 20px;
}

.openbuilt-dashboard__header h2 {
	margin: 0 0 8px;
	font-size: 22px;
	font-weight: 600;
}

.openbuilt-dashboard__lead {
	margin: 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.openbuilt-dashboard__columns {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 16px;
}

@media (max-width: 900px) {
	.openbuilt-dashboard__columns {
		grid-template-columns: 1fr;
	}
}

.openbuilt-dashboard__placeholder-list {
	margin: 0;
	padding-left: 1.2em;
	line-height: 1.6;
}

.openbuilt-dashboard__hint {
	margin: 0;
	line-height: 1.5;
	color: var(--color-text-maxcontrast);
}
</style>
