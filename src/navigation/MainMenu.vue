<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem
				:name="t('openbuilt', 'Dashboard')"
				:to="{ name: 'Dashboard' }"
				:exact="true">
				<template #icon>
					<HomeIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('openbuilt', 'Templates')"
				:to="{ name: 'TemplateGallery' }">
				<template #icon>
					<ViewGridOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('openbuilt', 'Documentation')"
				@click="openLink('https://conduction.nl', '_blank')">
				<template #icon>
					<BookOpenVariantOutline :size="20" />
				</template>
			</NcAppNavigationItem>
		</template>
		<template #footer>
			<NcAppNavigationItem
				:name="t('openbuilt', 'Settings')"
				:to="{ name: 'Settings' }">
				<template #icon>
					<CogIcon :size="20" />
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationItem } from '@nextcloud/vue'
import BookOpenVariantOutline from 'vue-material-design-icons/BookOpenVariantOutline.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import ViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'

export default {
	name: 'MainMenu',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		BookOpenVariantOutline,
		CogIcon,
		HomeIcon,
		ViewGridOutline,
	},
	methods: {
		openLink(url, target = '_blank') {
			window.open(url, target)
		},
	},
}
</script>
