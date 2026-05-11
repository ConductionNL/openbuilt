<?php

/**
 * OpenBuilt Applications Controller
 *
 * Serves the per-virtual-app manifest endpoint AND the thin-glue
 * clone-from-template action (REQ-OBTC-004 / REQ-OBTC-005). Per
 * design.md Decision 6 + ADR-032 these are the ONLY app-local
 * controller surfaces — all other CRUD on Application and
 * BuiltAppRoute objects is delegated to OpenRegister's REST API
 * directly (ADR-022).
 *
 * Hybrid register model: ApplicationTemplate lives in the shared
 * `openbuilt` register; cloned user schemas land in a per-app
 * `openbuilt-{newSlug}` register so each cloned app owns its own
 * schema namespace.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Controller
 * @package  OCA\OpenBuilt\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\OpenBuilt\Controller;

use OCA\OpenBuilt\AppInfo\Application;
use OCA\OpenRegister\Db\RegisterMapper;
use OCA\OpenRegister\Db\SchemaMapper;
use OCA\OpenRegister\Service\ObjectService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Controller for the OpenBuilt manifest endpoint and template clone action.
 */
class ApplicationsController extends Controller
{


    /**
     * Constructor.
     *
     * @param IRequest        $request        The current HTTP request
     * @param LoggerInterface $logger         PSR logger for diagnostics
     * @param ObjectService   $objectService  OpenRegister object service (hard dep via info.xml)
     * @param RegisterMapper  $registerMapper Resolves slugs/UUIDs to numeric register IDs
     * @param SchemaMapper    $schemaMapper   Resolves slugs/UUIDs to numeric schema IDs and creates schemas
     * @param IUserSession    $userSession    The Nextcloud user session (for owner tagging)
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private readonly LoggerInterface $logger,
        private readonly ObjectService $objectService,
        private readonly RegisterMapper $registerMapper,
        private readonly SchemaMapper $schemaMapper,
        private readonly IUserSession $userSession,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);

    }//end __construct()


    /**
     * Return the stored manifest JSON blob for a given virtual-app slug.
     *
     * Lookup path: slug → BuiltAppRoute → applicationUuid → Application →
     * manifest. The manifest is returned UNWRAPPED (no OR envelope) so
     * useAppManifest in @conduction/nextcloud-vue consumes it directly.
     *
     * @param string $slug The virtual-app slug from the URL
     *
     * @return JSONResponse The manifest blob, or a 404 envelope when not found
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getManifest(string $slug): JSONResponse
    {
        try {
            // Resolve register + schema slugs to numeric IDs. OR's searchObjects
            // expects numeric IDs in @self; the slug-resolution shortcut isn't
            // applied at this layer (verified during smoke-test 2026-05-11).
            // _multitenancy=false bypasses the org filter on the LOOKUP only —
            // object-level multitenancy is still enforced via searchObjects below.
            $registerId  = $this->registerMapper->find('openbuilt', _multitenancy: false)->getId();
            $routeSchema = $this->schemaMapper->find('built-app-route', _multitenancy: false)->getId();

            // Step 1 — resolve slug → applicationUuid via the BuiltAppRoute index.
            $routeResults = $this->objectService->searchObjects(
                query: [
                    '@self' => [
                        'register' => $registerId,
                        'schema'   => $routeSchema,
                    ],
                    'slug'  => $slug,
                ]
            );

            if (empty($routeResults) === true) {
                $this->logger->debug('OpenBuilt: no BuiltAppRoute found for slug='.$slug);
                return new JSONResponse(
                    data: ['error' => 'not_found', 'message' => 'No published virtual app found for slug '.$slug],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            // FindAll renders entities; result entries may be ObjectEntity or arrays.
            $route           = $this->normaliseObject(object: $routeResults[0]);
            $applicationUuid = ($route['applicationUuid'] ?? null);

            if ($applicationUuid === null) {
                $this->logger->warning('OpenBuilt: BuiltAppRoute for slug '.$slug.' is missing applicationUuid');
                return new JSONResponse(
                    data: ['error' => 'inconsistent_state', 'message' => 'Route exists but has no applicationUuid'],
                    statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            // Step 2 — load the Application object.
            $application = $this->objectService->find(
                id: $applicationUuid,
                register: 'openbuilt',
                schema: 'application'
            );

            if ($application === null) {
                $this->logger->warning('OpenBuilt: Application '.$applicationUuid.' (for slug '.$slug.') not found');
                return new JSONResponse(
                    data: ['error' => 'inconsistent_state', 'message' => 'Route points to an Application that does not exist'],
                    statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            $applicationArray = $this->normaliseObject(object: $application);
            $manifest         = ($applicationArray['manifest'] ?? null);

            if ($manifest === null) {
                $this->logger->warning('OpenBuilt: Application '.$applicationUuid.' has no manifest property');
                return new JSONResponse(
                    data: ['error' => 'no_manifest', 'message' => 'Application has no manifest'],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            // Return the manifest UNWRAPPED — useAppManifest expects the bare object.
            return new JSONResponse(data: $manifest, statusCode: Http::STATUS_OK);
        } catch (\Throwable $e) {
            $this->logger->error('OpenBuilt: getManifest failed for slug '.$slug.': '.$e->getMessage(), ['exception' => $e]);
            return new JSONResponse(
                data: ['error' => 'internal_error', 'message' => 'Failed to resolve manifest'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try

    }//end getManifest()


    /**
     * Clone an Application from a template.
     *
     * Reads the ApplicationTemplate identified by $templateSlug, creates a
     * per-app `openbuilt-{newSlug}` register, deep-copies its companion JSON
     * schemas into that per-app register (REQ-OBTC-005 / hybrid register
     * model), rewrites manifest schema refs to the new slug, and creates a
     * new Application record in the shared `openbuilt` register, tagged
     * with the caller's UID (multi-user isolation).
     *
     * @param string $templateSlug The source template slug
     *
     * @return JSONResponse The new application's uuid + slug, or an error envelope
     */
    #[NoAdminRequired]
    public function createFromTemplate(string $templateSlug): JSONResponse
    {
        // 1. Auth check.
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(
                data: ['error' => 'unauthenticated'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        $ownerUid = $user->getUID();

        // 2. Validate request body.
        $validation = $this->validateCloneRequest(body: $this->request->getParams());
        if (is_array($validation) === true) {
            return new JSONResponse(data: $validation['error'], statusCode: $validation['status']);
        }

        [$name, $newSlug] = $validation;

        // 3. Resolve shared register + schemas.
        try {
            $sharedRegisterId    = $this->registerMapper->find('openbuilt', _multitenancy: false)->getId();
            $templateSchemaId    = $this->schemaMapper->find('application-template', _multitenancy: false)->getId();
            $applicationSchemaId = $this->schemaMapper->find('application', _multitenancy: false)->getId();
        } catch (\Throwable $e) {
            $this->logger->error('OpenBuilt: register/schema resolution failed', ['exception' => $e->getMessage()]);
            return new JSONResponse(
                data: ['error' => 'not_configured', 'detail' => 'OpenBuilt register/schemas not initialised'],
                statusCode: Http::STATUS_SERVICE_UNAVAILABLE
            );
        }

        // 4. Lookup template.
        $template = $this->lookupOne(
            registerId: $sharedRegisterId,
            schemaId: $templateSchemaId,
            slug: $templateSlug
        );
        if ($template === null) {
            return new JSONResponse(
                data: ['error' => 'template_not_found', 'slug' => $templateSlug],
                statusCode: Http::STATUS_NOT_FOUND
            );
        }

        // 5. Slug-collision check, scoped to the caller's UID (multi-user isolation).
        $existing = $this->lookupOne(
            registerId: $sharedRegisterId,
            schemaId: $applicationSchemaId,
            slug: $newSlug,
            owner: $ownerUid
        );
        if ($existing !== null) {
            return new JSONResponse(
                data: ['error' => 'slug_collision', 'slug' => $newSlug],
                statusCode: Http::STATUS_CONFLICT
            );
        }

        // 6. Build rewrite map (source-slug → prefixed-slug) and rewrite manifest refs.
        $companionInput = $this->extractCompanionSchemas(template: $template);
        $rewriteMap     = $this->buildRewriteMap(companions: $companionInput, newSlug: $newSlug);
        $manifestRaw    = ($template['manifest'] ?? null);
        $manifest       = is_array($manifestRaw) === true ? $manifestRaw : [];
        $manifest       = $this->rewriteSchemaRefs(node: $manifest, map: $rewriteMap);

        // 7. Provision per-app register + clone companion schemas into it.
        try {
            $perAppRegister = $this->provisionPerAppRegister(newSlug: $newSlug, ownerUid: $ownerUid);
            $createdSchemaIds = $this->cloneCompanionSchemas(
                companions: $companionInput,
                rewriteMap: $rewriteMap,
                perAppRegister: $perAppRegister
            );
        } catch (\Throwable $e) {
            $this->logger->error('OpenBuilt: companion-schema clone failed', ['exception' => $e->getMessage()]);
            return new JSONResponse(
                data: ['error' => 'clone_failed', 'detail' => 'Failed to provision per-app register/schemas'],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        // 8. Create the Application record (in shared register), tagged with owner.
        try {
            $application = [
                'name'           => $name,
                'slug'           => $newSlug,
                'status'         => 'draft',
                'version'        => '0.1.0',
                'owner'          => $ownerUid,
                'manifest'       => $manifest,
                'templateOrigin' => [
                    'slug'    => (string) ($template['slug'] ?? $templateSlug),
                    'version' => (string) ($template['version'] ?? ''),
                ],
            ];
            $created = $this->objectService->saveObject(
                object: $application,
                register: $sharedRegisterId,
                schema: $applicationSchemaId
            );
        } catch (\Throwable $e) {
            $this->logger->error('OpenBuilt: application save failed', ['exception' => $e->getMessage()]);
            return new JSONResponse(
                data: ['error' => 'clone_failed', 'detail' => $e->getMessage()],
                statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        $createdArray = $this->normaliseObject(object: $created);
        $uuid         = ($createdArray['uuid'] ?? $createdArray['id'] ?? null);

        return new JSONResponse(
            data: [
                'uuid'             => $uuid,
                'slug'             => $newSlug,
                'register'         => $perAppRegister->getSlug(),
                'companionSchemas' => $createdSchemaIds,
            ],
            statusCode: Http::STATUS_CREATED
        );

    }//end createFromTemplate()


    /**
     * Validate the clone-from-template request body.
     *
     * @param array<string,mixed> $body The request params
     *
     * @return array{0:string,1:string}|array{error:array<string,mixed>,status:int}
     *         Either [name, slug] on success, or an error+status envelope.
     */
    private function validateCloneRequest(array $body): array
    {
        $name = (string) ($body['name'] ?? '');
        $slug = (string) ($body['slug'] ?? '');

        if ($name === '' || $slug === '' || preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $slug) !== 1) {
            return [
                'error'  => ['error' => 'invalid_request', 'detail' => 'name and kebab-case slug required'],
                'status' => Http::STATUS_BAD_REQUEST,
            ];
        }

        if (strlen($slug) > 32) {
            return [
                'error'  => ['error' => 'slug_too_long', 'detail' => 'slug must be <= 32 chars'],
                'status' => Http::STATUS_BAD_REQUEST,
            ];
        }

        return [$name, $slug];

    }//end validateCloneRequest()


    /**
     * Extract companionSchemas array from a template record.
     *
     * @param array<string,mixed> $template The template record
     *
     * @return array<int,array<string,mixed>>
     */
    private function extractCompanionSchemas(array $template): array
    {
        $companionRaw = ($template['companionSchemas'] ?? null);
        if (is_array($companionRaw) === false) {
            return [];
        }

        return array_values(
            array_filter(
                $companionRaw,
                static fn ($entry): bool => is_array($entry) === true && isset($entry['slug']) === true
            )
        );

    }//end extractCompanionSchemas()


    /**
     * Build the source-slug → prefixed-slug rewrite map.
     *
     * @param array<int,array<string,mixed>> $companions The companion schema blobs
     * @param string                         $newSlug    The new app slug used as prefix
     *
     * @return array<string,string>
     */
    private function buildRewriteMap(array $companions, string $newSlug): array
    {
        $map = [];
        foreach ($companions as $companion) {
            $sourceSlug = (string) $companion['slug'];
            $map[$sourceSlug] = $newSlug.'-'.$sourceSlug;
        }

        return $map;

    }//end buildRewriteMap()


    /**
     * Provision (or fetch existing) the per-app register `openbuilt-{newSlug}`.
     *
     * Per the hybrid register model, each cloned app gets its own register so
     * companion schemas don't collide across apps.
     *
     * @param string $newSlug  The new app slug
     * @param string $ownerUid The Nextcloud UID of the owner
     *
     * @return \OCA\OpenRegister\Db\Register
     */
    private function provisionPerAppRegister(string $newSlug, string $ownerUid): \OCA\OpenRegister\Db\Register
    {
        $registerSlug = 'openbuilt-'.$newSlug;

        try {
            return $this->registerMapper->find($registerSlug, _multitenancy: false);
        } catch (\Throwable) {
            // Register does not exist yet — create it.
        }

        return $this->registerMapper->createFromArray(
            [
                'slug'        => $registerSlug,
                'title'       => 'OpenBuilt — '.$newSlug,
                'description' => 'Per-app schema namespace for OpenBuilt app `'.$newSlug.'` (owner: '.$ownerUid.').',
                'version'     => '0.1.0',
                'schemas'     => [],
            ]
        );

    }//end provisionPerAppRegister()


    /**
     * Clone companion schemas into the per-app register.
     *
     * Critical fix: companion schemas are CREATED AS SCHEMAS via SchemaMapper
     * (NOT saved as Application objects, which was the bug at the previous
     * line 168). The per-app register's `schemas` array is updated to include
     * the new schema IDs.
     *
     * @param array<int,array<string,mixed>> $companions     The companion schema blobs from the template
     * @param array<string,string>           $rewriteMap     Source-slug → prefixed-slug map
     * @param \OCA\OpenRegister\Db\Register  $perAppRegister The target per-app register
     *
     * @return array<int,int> List of created schema IDs
     */
    private function cloneCompanionSchemas(
        array $companions,
        array $rewriteMap,
        \OCA\OpenRegister\Db\Register $perAppRegister
    ): array {
        $createdIds = [];

        foreach ($companions as $companion) {
            $sourceSlug = (string) $companion['slug'];
            if (isset($rewriteMap[$sourceSlug]) === false) {
                continue;
            }

            $schemaPayload         = $companion;
            $schemaPayload['slug'] = $rewriteMap[$sourceSlug];
            // Ensure a stable version (templates ship with their own; default to 0.1.0).
            if (isset($schemaPayload['version']) === false) {
                $schemaPayload['version'] = '0.1.0';
            }

            $schema       = $this->schemaMapper->createFromArray(object: $schemaPayload);
            $createdIds[] = $schema->getId();
        }

        if ($createdIds !== []) {
            $existing = $perAppRegister->getSchemas();
            $perAppRegister->setSchemas(array_values(array_unique(array_merge($existing, $createdIds))));
            $this->registerMapper->update($perAppRegister);
        }

        return $createdIds;

    }//end cloneCompanionSchemas()


    /**
     * Recursively rewrite manifest page-config schema references.
     *
     * @param mixed                $node The manifest node
     * @param array<string,string> $map  Map of source-slug => prefixed-slug
     *
     * @return mixed The rewritten node
     */
    private function rewriteSchemaRefs(mixed $node, array $map): mixed
    {
        if (is_array($node) === false) {
            return $node;
        }

        foreach ($node as $key => $value) {
            if (($key === 'schema' || $key === 'relatedSchema')
                && is_string($value) === true
                && isset($map[$value]) === true
            ) {
                $node[$key] = $map[$value];
                continue;
            }

            if (is_array($value) === true) {
                $node[$key] = $this->rewriteSchemaRefs(node: $value, map: $map);
            }
        }

        return $node;

    }//end rewriteSchemaRefs()


    /**
     * Look up a single object by slug (optionally scoped by owner).
     *
     * @param int|string  $registerId The register ID
     * @param int|string  $schemaId   The schema ID
     * @param string      $slug       The slug to look up
     * @param string|null $owner      Optional owner UID (multi-user isolation scope)
     *
     * @return array<string,mixed>|null
     */
    private function lookupOne(
        int | string $registerId,
        int | string $schemaId,
        string $slug,
        ?string $owner = null
    ): ?array {
        try {
            $query = [
                '@self' => [
                    'register' => $registerId,
                    'schema'   => $schemaId,
                ],
                'slug'  => $slug,
            ];

            if ($owner !== null) {
                $query['owner'] = $owner;
            }

            $results = $this->objectService->searchObjects(query: $query);

            if (is_array($results) === false || count($results) === 0) {
                return null;
            }

            return $this->normaliseObject(object: $results[0]);
        } catch (\Throwable $e) {
            $this->logger->warning('OpenBuilt: lookup failed', ['exception' => $e->getMessage()]);
            return null;
        }

    }//end lookupOne()


    /**
     * Coerce an OR result entry (ObjectEntity or array) to a plain associative array.
     *
     * @param mixed $object The OR object/result entry.
     *
     * @return array<string, mixed>
     */
    private function normaliseObject(mixed $object): array
    {
        if (is_array($object) === true) {
            return $object;
        }

        if (is_object($object) === true && method_exists($object, 'jsonSerialize') === true) {
            $serialised = $object->jsonSerialize();
            if (is_array($serialised) === true) {
                return $serialised;
            }
        }

        if (is_object($object) === true && method_exists($object, 'getObject') === true) {
            $inner = $object->getObject();
            if (is_array($inner) === true) {
                return $inner;
            }
        }

        return [];

    }//end normaliseObject()


}//end class
