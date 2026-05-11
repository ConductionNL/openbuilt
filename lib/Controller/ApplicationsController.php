<?php
// SPDX-License-Identifier: EUPL-1.2

/**
 * OpenBuilt Applications Controller
 *
 * Serves the per-virtual-app manifest endpoint. Per design.md Decision 6
 * this is the ONLY app-local controller surface — all CRUD on Application
 * and BuiltAppRoute objects is delegated to OpenRegister's REST API
 * directly (ADR-022).
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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Controller for the OpenBuilt manifest endpoint.
 */
class ApplicationsController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest        $request The current HTTP request
     * @param LoggerInterface $logger  PSR logger for diagnostics
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private readonly LoggerInterface $logger,
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
            // OpenRegister provides the OpenRegisterService and ObjectService via DI;
            // resolve at request time to avoid hard-coupling at app boot when OR is
            // not yet installed (per ADR-022).
            $objectService = Server::get('OCA\\OpenRegister\\Service\\ObjectService');

            // Step 1 — resolve slug → applicationUuid via the BuiltAppRoute index.
            $routeResults = $objectService->getObjects(
                register: 'openbuilt',
                schema: 'built-app-route',
                filters: ['slug' => $slug],
                limit: 1
            );

            if (empty($routeResults) === true) {
                $this->logger->debug('OpenBuilt: no BuiltAppRoute found for slug='.$slug);
                return new JSONResponse(
                    data: ['error' => 'not_found', 'message' => 'No published virtual app found for slug '.$slug],
                    statusCode: Http::STATUS_NOT_FOUND
                );
            }

            $applicationUuid = ($routeResults[0]['applicationUuid'] ?? null);

            if ($applicationUuid === null) {
                $this->logger->warning('OpenBuilt: BuiltAppRoute for slug '.$slug.' is missing applicationUuid');
                return new JSONResponse(
                    data: ['error' => 'inconsistent_state', 'message' => 'Route exists but has no applicationUuid'],
                    statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            // Step 2 — load the Application object and return its manifest.
            $application = $objectService->getObject(
                register: 'openbuilt',
                schema: 'application',
                uuid: $applicationUuid
            );

            if ($application === null) {
                $this->logger->warning('OpenBuilt: Application '.$applicationUuid.' (for slug '.$slug.') not found');
                return new JSONResponse(
                    data: ['error' => 'inconsistent_state', 'message' => 'Route points to an Application that does not exist'],
                    statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }

            $manifest = ($application['manifest'] ?? null);

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
}//end class
