<?php

/**
 * OpenBuilt Seed Hello World Repair Step
 *
 * Seeds the canonical `hello-world` virtual app on install/upgrade so
 * the install is testable out of the box. Idempotent — guarded on
 * existing-slug; re-running on a seeded install is a no-op.
 *
 * Per design.md Seed Data section (ADR-001 compliance), this step
 * creates one published Application with slug `hello-world` plus its
 * companion `hello-message` schema's three sample objects. The
 * Application's x-openregister-lifecycle handles the BuiltAppRoute
 * upkeep on publish.
 *
 * @category Repair
 * @package  OCA\OpenBuilt\Repair
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

namespace OCA\OpenBuilt\Repair;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Repair step that seeds the hello-world virtual app + sample messages.
 */
class SeedHelloWorld implements IRepairStep
{
    private const SEED_SLUG = 'hello-world';

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger Logger for diagnostics
     *
     * @return void
     */
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }//end __construct()


    /**
     * Get the name of this repair step.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Seed the canonical hello-world virtual app and sample messages';
    }//end getName()


    /**
     * Run the repair step to seed the hello-world virtual app.
     *
     * @param IOutput $output The output interface for progress reporting
     *
     * @return void
     */
    public function run(IOutput $output): void
    {
        $output->info('Seeding hello-world virtual app...');

        try {
            $objectService = Server::get('OCA\\OpenRegister\\Service\\ObjectService');
        } catch (\Throwable $e) {
            $output->warning('OpenRegister not available; skipping seed.');
            $this->logger->warning(
                'OpenBuilt: SeedHelloWorld skipped — OpenRegister not available',
                ['exception' => $e->getMessage()]
            );
            return;
        }

        try {
            // Idempotency guard — if a hello-world Application already exists, do nothing.
            $existing = $objectService->getObjects(
                register: 'openbuilt',
                schema: 'application',
                filters: ['slug' => self::SEED_SLUG],
                limit: 1
            );

            if (empty($existing) === false) {
                $output->info('hello-world Application already exists; skipping seed.');
                return;
            }

            // Create the Application object with the canonical hello-world manifest.
            $application = $objectService->saveObject(
                object: [
                    'slug'        => self::SEED_SLUG,
                    'name'        => 'Hello World',
                    'description' => 'The canonical seed virtual app for OpenBuilt. Exercises index + detail + form page types.',
                    'version'     => '0.1.0',
                    'status'      => 'published',
                    'manifest'    => $this->buildHelloWorldManifest(),
                ],
                register: 'openbuilt',
                schema: 'application'
            );

            $output->info('Created hello-world Application.');

            // Seed three sample HelloMessage objects.
            foreach ($this->buildSampleMessages() as $message) {
                $objectService->saveObject(
                    object: $message,
                    register: 'openbuilt',
                    schema: 'hello-message'
                );
            }

            $output->info('Seeded three sample HelloMessage objects.');

            $this->logger->info('OpenBuilt: hello-world virtual app seeded successfully');
        } catch (\Throwable $e) {
            $output->warning('Could not seed hello-world: '.$e->getMessage());
            $this->logger->error(
                'OpenBuilt: SeedHelloWorld failed',
                ['exception' => $e->getMessage()]
            );
        }//end try

    }//end run()


    /**
     * Build the canonical hello-world manifest.
     *
     * Per design.md Seed Data: exercises index + detail + form page types
     * against the seeded `hello-message` schema. Labels and titles use
     * i18n keys consumed by the consuming app's t() (ADR-024 §6, ADR-007).
     *
     * @return array<string, mixed>
     */
    private function buildHelloWorldManifest(): array
    {
        return [
            'version'      => '1.0.0',
            'dependencies' => ['openregister'],
            'menu'         => [
                [
                    'id'    => 'Messages',
                    'label' => 'openbuilt.helloworld.menu.messages',
                    'icon'  => 'icon-comment',
                    'route' => 'Messages',
                    'order' => 1,
                ],
            ],
            'pages'        => [
                [
                    'id'     => 'Messages',
                    'route'  => '/',
                    'type'   => 'index',
                    'title'  => 'openbuilt.helloworld.title.messages',
                    'config' => [
                        'register' => 'openbuilt',
                        'schema'   => 'hello-message',
                        'columns'  => ['title', 'body', '@self.created'],
                    ],
                ],
                [
                    'id'     => 'MessageDetail',
                    'route'  => '/messages/:id',
                    'type'   => 'detail',
                    'title'  => 'openbuilt.helloworld.title.message',
                    'config' => [
                        'register' => 'openbuilt',
                        'schema'   => 'hello-message',
                    ],
                ],
                [
                    'id'     => 'MessageCreate',
                    'route'  => '/messages/new',
                    'type'   => 'form',
                    'title'  => 'openbuilt.helloworld.title.create',
                    'config' => [
                        'register'       => 'openbuilt',
                        'schema'         => 'hello-message',
                        'mode'           => 'create',
                        'submitEndpoint' => '/index.php/apps/openregister/api/objects/openbuilt/hello-message',
                    ],
                ],
            ],
        ];

    }//end buildHelloWorldManifest()


    /**
     * Build the three sample HelloMessage objects.
     *
     * @return array<int, array<string, string>>
     */
    private function buildSampleMessages(): array
    {
        return [
            [
                'title' => 'Welcome to OpenBuilt',
                'body'  => 'This message is rendered by your first virtual app. The page you see right now is built entirely from a JSON manifest stored in OpenRegister.',
            ],
            [
                'title' => 'Edit me',
                'body'  => 'Open the OpenBuilt shell, find the hello-world application, and edit its manifest to change what you see here. Reload the page to see the change.',
            ],
            [
                'title' => 'Built from a manifest',
                'body'  => 'Everything in this virtual app — the menu, the pages, the columns, the form — came from a JSON manifest. No PHP was authored for hello-world specifically.',
            ],
        ];

    }//end buildSampleMessages()
}//end class
