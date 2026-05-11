<?php

/**
 * Unit tests for ApplicationsController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Test
 * @package  OCA\OpenBuilt\Tests\Unit\Controller
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

namespace OCA\OpenBuilt\Tests\Unit\Controller;

use OCA\OpenBuilt\Controller\ApplicationsController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for ApplicationsController::getManifest.
 */
class ApplicationsControllerTest extends TestCase
{
    /**
     * Controller under test.
     *
     * @var ApplicationsController
     */
    private ApplicationsController $controller;

    /**
     * Mock OR ObjectService — typed as object since the real class lives in another app.
     *
     * @var MockObject
     */
    private MockObject $objectService;

    /**
     * Mock logger.
     *
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface&MockObject $logger;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $request             = $this->createMock(IRequest::class);
        $this->logger        = $this->createMock(LoggerInterface::class);
        $this->objectService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['searchObjects', 'find'])
            ->getMock();

        // RegisterMapper + SchemaMapper mocks: both have ->find()->getId() chains used by the controller.
        $registerEntity = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getId'])
            ->getMock();
        $registerEntity->method('getId')->willReturn(926);
        $registerMapper = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['find'])
            ->getMock();
        $registerMapper->method('find')->willReturn($registerEntity);

        $schemaEntity = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getId'])
            ->getMock();
        $schemaEntity->method('getId')->willReturn(1635);
        $schemaMapper = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['find'])
            ->getMock();
        $schemaMapper->method('find')->willReturn($schemaEntity);

        $this->controller = new ApplicationsController(
            request: $request,
            logger: $this->logger,
            objectService: $this->objectService,
            registerMapper: $registerMapper,
            schemaMapper: $schemaMapper,
        );
    }//end setUp()

    /**
     * Happy path — slug resolves to a published Application; manifest is returned unwrapped.
     *
     * @return void
     */
    public function testGetManifestReturnsManifestUnwrapped(): void
    {
        $manifest = [
            'version' => '1.0.0',
            'menu'    => [],
            'pages'   => [['id' => 'p1', 'route' => '/', 'type' => 'index']],
        ];

        $this->objectService->method('searchObjects')
            ->willReturn([['applicationUuid' => 'abc-123']]);

        $this->objectService->method('find')
            ->willReturn(['manifest' => $manifest]);

        $result = $this->controller->getManifest(slug: 'hello-world');

        self::assertInstanceOf(JSONResponse::class, $result);
        self::assertSame(Http::STATUS_OK, $result->getStatus());
        self::assertSame($manifest, $result->getData());
    }//end testGetManifestReturnsManifestUnwrapped()

    /**
     * Unknown slug → 404 with not_found error code.
     *
     * @return void
     */
    public function testGetManifestReturns404WhenSlugUnknown(): void
    {
        $this->objectService->method('searchObjects')->willReturn([]);

        $result = $this->controller->getManifest(slug: 'no-such-app');

        self::assertSame(Http::STATUS_NOT_FOUND, $result->getStatus());
        $data = $result->getData();
        self::assertSame('not_found', $data['error']);
    }//end testGetManifestReturns404WhenSlugUnknown()

    /**
     * Inconsistent state — route exists but no applicationUuid → 500.
     *
     * @return void
     */
    public function testGetManifestReturns500WhenRouteMissingApplicationUuid(): void
    {
        $this->objectService->method('searchObjects')
            ->willReturn([['slug' => 'hello-world']]);

        $this->logger->expects(self::atLeastOnce())->method('warning');

        $result = $this->controller->getManifest(slug: 'hello-world');

        self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $result->getStatus());
        $data = $result->getData();
        self::assertSame('inconsistent_state', $data['error']);
    }//end testGetManifestReturns500WhenRouteMissingApplicationUuid()
}//end class
