<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Command;

use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateConfig;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateService;

#[AsCommand(
    name: 'wsc:variant:update',
    description: 'Updates variant names and product numbers based on parent product and options'
)]
class UpdateVariantCommand extends Command
{
    public function __construct(
        private readonly VariantUpdateService $variantUpdateService
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        // Check if --product-numbers is provided
        $productNumbersInput = $input->getOption('product-numbers');
        if (empty($productNumbersInput)) {
            $io->error('The --product-numbers option is required. Please provide at least one product number.');
            return Command::FAILURE;
        }

        // Parse product numbers
        $productNumbers = array_map('trim', explode(',', $productNumbersInput));
        $productNumbers = array_filter($productNumbers);

        if (empty($productNumbers)) {
            $io->error('No valid product numbers provided.');
            return Command::FAILURE;
        }

        // Create config from options
        $config = VariantUpdateConfig::fromArray([
            'dry-run' => $input->getOption('dry-run'),
            'name-only' => $input->getOption('name-only'),
            'number-only' => $input->getOption('number-only'),
        ]);

        if ($config->dryRun) {
            $io->warning('DRY RUN MODE - No changes will be saved');
        }

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
}
