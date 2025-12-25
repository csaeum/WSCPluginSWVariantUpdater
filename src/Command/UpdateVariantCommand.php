<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use WSCPlugin\SWVariantUpdater\Message\UpdateVariantsMessage;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateConfig;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateService;

#[AsCommand(
    name: 'wsc:variant:update',
    description: 'Updates variant names and product numbers based on parent product and options'
)]
class UpdateVariantCommand extends Command
{
    public function __construct(
        private readonly VariantUpdateService $variantUpdateService,
        private readonly MessageBusInterface $messageBus,
        private readonly SystemConfigService $systemConfigService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'product-numbers',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of parent product numbers'
            )
            ->addOption(
                'all-products',
                null,
                InputOption::VALUE_NONE,
                'Update ALL products with variants (requires confirmation)'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be changed without saving'
            )
            ->addOption(
                'name-only',
                null,
                InputOption::VALUE_NONE,
                'Update only the product name'
            )
            ->addOption(
                'number-only',
                null,
                InputOption::VALUE_NONE,
                'Update only the product number'
            )
            ->addOption(
                'sync',
                null,
                InputOption::VALUE_NONE,
                'Execute synchronously (default: async via message queue)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        // Validation: Either --product-numbers OR --all-products
        $productNumbersInput = $input->getOption('product-numbers');
        $allProducts = $input->getOption('all-products');

        if (!$productNumbersInput && !$allProducts) {
            $io->error('Either --product-numbers or --all-products is required.');
            return Command::FAILURE;
        }

        if ($productNumbersInput && $allProducts) {
            $io->error('Cannot use both --product-numbers and --all-products together.');
            return Command::FAILURE;
        }

        // Determine product numbers
        if ($allProducts) {
            // Mode A: Load all products
            $productNumbers = $this->getAllProductNumbers($context);

            if (empty($productNumbers)) {
                $io->warning('No products with variants found.');
                return Command::SUCCESS;
            }

            $io->warning([
                'You are about to update ALL products with variants!',
                sprintf('Found: %d products', \count($productNumbers)),
            ]);

            if (!$io->confirm('Do you want to continue?', false)) {
                $io->info('Cancelled.');
                return Command::SUCCESS;
            }
        } else {
            // Mode B: Specific products
            $productNumbers = array_map('trim', explode(',', $productNumbersInput));
            $productNumbers = array_filter($productNumbers);

            if (empty($productNumbers)) {
                $io->error('No valid product numbers provided.');
                return Command::FAILURE;
            }
        }

        // Create config with SystemConfig fallback
        $config = VariantUpdateConfig::fromRequestWithDefaults(
            [
                'dry-run' => $input->getOption('dry-run'),
                'name-only' => $input->getOption('name-only'),
                'number-only' => $input->getOption('number-only'),
            ],
            $this->systemConfigService
        );

        if ($config->dryRun) {
            $io->warning('DRY RUN MODE - No changes will be saved');
        }

        $syncMode = $input->getOption('sync');

        // Execute sync or async
        if ($syncMode) {
            return $this->executeSynchronous($io, $productNumbers, $config, $context);
        }

        return $this->executeAsynchronous($io, $productNumbers, $config, $context);
    }

    /**
     * Execute synchronously (direct processing).
     */
    private function executeSynchronous(
        SymfonyStyle $io,
        array $productNumbers,
        VariantUpdateConfig $config,
        Context $context
    ): int {
        $io->info('Executing synchronously...');

        // Process variants using service
        $result = $this->variantUpdateService->updateVariants(
            $productNumbers,
            $config,
            $context
        );

        // Display detailed changes
        $this->displayChanges($io, $result);

        // Display warnings
        if ($result->hasWarnings()) {
            $io->warning('Warnings occurred during processing:');
            foreach ($result->getWarnings() as $productNumber => $warning) {
                $io->text("  - {$productNumber}: {$warning}");
            }
            $io->newLine();
        }

        // Display errors
        if ($result->hasErrors()) {
            $io->error('Errors occurred during processing:');
            foreach ($result->getErrors() as $productNumber => $error) {
                $io->text("  - {$productNumber}: {$error}");
            }
            $io->newLine();
        }

        // Display summary statistics
        $this->displaySummary($io, $result, $config);

        return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Execute asynchronously (via message queue).
     */
    private function executeAsynchronous(
        SymfonyStyle $io,
        array $productNumbers,
        VariantUpdateConfig $config,
        Context $context
    ): int {
        $batchId = 'batch_' . Uuid::randomHex();

        $io->info('Executing asynchronously via message queue...');

        // Create and dispatch message
        $message = new UpdateVariantsMessage(
            $productNumbers,
            $config,
            $batchId,
            $context
        );

        $this->messageBus->dispatch($message);

        $io->success([
            'Message queue job created successfully!',
            sprintf('Batch ID: %s', $batchId),
            sprintf('Products: %d', \count($productNumbers)),
            '',
            'The update will be processed in the background.',
            'Check progress in the Shopware admin or logs.',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Display detailed changes for each variant.
     */
    private function displayChanges(SymfonyStyle $io, \WSCPlugin\SWVariantUpdater\Service\VariantUpdateResult $result): void
    {
        foreach ($result->getVariantChanges() as $variantId => $changes) {
            if (isset($changes['name'])) {
                $io->text("  Name: '{$changes['name']['old']}' -> '{$changes['name']['new']}'");
            }
            if (isset($changes['number'])) {
                $io->text("  Number: '{$changes['number']['old']}' -> '{$changes['number']['new']}'");
            }
            $io->newLine();
        }
    }

    /**
     * Display summary statistics.
     */
    private function displaySummary(SymfonyStyle $io, \WSCPlugin\SWVariantUpdater\Service\VariantUpdateResult $result, VariantUpdateConfig $config): void
    {
        $messages = [
            sprintf('Products processed: %d', $result->getProductCount()),
            sprintf('Total variants checked: %d', $result->getTotalVariants()),
            sprintf('Variants updated: %d', $result->getVariantsUpdated()),
        ];

        if (!$config->numberOnly) {
            $messages[] = sprintf('Names changed: %d', $result->getNameChanges());
        }

        if (!$config->nameOnly) {
            $messages[] = sprintf('Numbers changed: %d', $result->getNumberChanges());
        }

        if ($result->hasWarnings()) {
            $messages[] = sprintf('Warnings: %d', \count($result->getWarnings()));
        }

        if ($result->hasErrors()) {
            $messages[] = sprintf('Errors: %d', \count($result->getErrors()));
        }

        if ($config->dryRun) {
            $messages[] = '(DRY RUN - no changes saved)';
        }

        $io->success($messages);
    }

    /**
     * Get all product numbers for products with variants.
     *
     * @return array<string>
     */
    private function getAllProductNumbers(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->addFilter(new RangeFilter('childCount', [
            RangeFilter::GT => 0,
        ]));

        $products = $this->variantUpdateService->productRepository->search($criteria, $context);

        return array_map(fn ($p) => $p->getProductNumber(), $products->getElements());
    }
}
