// SPDX-License-Identifier: EUPL-1.2
/**
 * Object store for OpenBuilt — powered by @conduction/nextcloud-vue.
 *
 * Uses createObjectStore('object') so the same Pinia store ID is shared
 * across views. The full implementation (CRUD, pagination, caching,
 * resolveReferences, fetchSchema) lives in the shared library.
 *
 * Per ADR-004 + the memory rule "Store pattern guidance — Do not use
 * custom stores; use Options API with createObjectStore", this store
 * replaces the prior hand-rolled defineStore implementation.
 */
import { createObjectStore } from '@conduction/nextcloud-vue'

export const useObjectStore = createObjectStore('object')
