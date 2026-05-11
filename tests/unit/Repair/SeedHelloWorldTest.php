<?php

/**
 * Unit tests for SeedHelloWorld repair step.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Test
 * @package  OCA\OpenBuilt\Tests\Unit\Repair
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

namespace OCA\OpenBuilt\Tests\Unit\Repair;

use OCA\OpenBuilt\Repair\SeedHelloWorld;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for SeedHelloWorld::run.
 */
class SeedHelloWorldTest extends TestCase
{
    /**
     * Mock logger.
     *
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface&MockObject $logger;

    /**
     * Mock OR ObjectService — typed as object since the real class lives in another app.
     *
     * @var MockObject
     */
    private MockObject $objectService;

    /**
     * Mock IOutput.
     *
     * @var IOutput&MockObject
     */
    private IOutput&MockObject $output;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->logger        = $this->createMock(LoggerInterface::class);
        $this->output        = $this->createMock(IOutput::class);
        $this->objectService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['findAll', 'saveObject'])
            ->getMock();
    }//end setUp()

    /**
     * Test that getName returns a non-empty descriptive name.
     *
     * @return void
     */
    public function testGetNameReturnsDescriptiveName(): void
    {
        $step = new SeedHelloWorld(logger: $this->logger, objectService: $this->objectService);

        $name = $step->getName();

        self::assertNotEmpty($name);
        self::assertStringContainsString('hello-world', $name);
    }//end testGetNameReturnsDescriptiveName()

    /**
     * Test idempotency — when an existing hello-world Application is found, saveObject is NOT called.
     *
     * @return void
     */
    public function testRunIsIdempotentWhenSeedAlreadyExists(): void
    {
        $this->objectService->expects(self::once())
            ->method('findAll')
            ->willReturn([['slug' => 'hello-world']]);

        $this->objectService->expects(self::never())
            ->method('saveObject');

        $this->output->expects(self::atLeastOnce())->method('info');

        $step = new SeedHelloWorld(logger: $this->logger, objectService: $this->objectService);
        $step->run($this->output);
    }//end testRunIsIdempotentWhenSeedAlreadyExists()

    /**
     * Test fresh-install path — when no existing hello-world exists, saveObject is called for the
     * Application plus three HelloMessage objects (4 total saves).
     *
     * @return void
     */
    public function testRunCreatesApplicationAndThreeMessagesOnFreshInstall(): void
    {
        $this->objectService->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $this->objectService->expects(self::exactly(4))
            ->method('saveObject');

        $step = new SeedHelloWorld(logger: $this->logger, objectService: $this->objectService);
        $step->run($this->output);
    }//end testRunCreatesApplicationAndThreeMessagesOnFreshInstall()
}//end class
