// SPDX-License-Identifier: EUPL-1.2
import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import Dashboard from '../views/Dashboard.vue'
import AdminRoot from '../views/settings/AdminRoot.vue'
import ApplicationEditor from '../views/ApplicationEditor.vue'
import BuilderHost from '../views/BuilderHost.vue'

Vue.use(Router)

export default new Router({
	mode: 'history',
	base: generateUrl('/apps/openbuilt'),
	routes: [
		{ path: '/', name: 'Dashboard', component: Dashboard },
		// Manifest editor (textarea v1). Visual editor lands in chain spec #5.
		{ path: '/applications', name: 'ApplicationEditor', component: ApplicationEditor },
		// Virtual-app host. The trailing wildcard forwards path segments to
		// the inner CnAppRoot's router (per design.md Decision 5).
		{ path: '/builder/:slug/:pathMatch(.*)?', name: 'BuilderHost', component: BuilderHost },
		{ path: '/settings', name: 'Settings', component: AdminRoot },
		{ path: '*', redirect: '/' },
	],
})
