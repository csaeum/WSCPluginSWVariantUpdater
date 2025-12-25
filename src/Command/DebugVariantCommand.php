<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Command;

use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WSCPlugin\SWVariantUpdater\Service\TwigTemplateRenderer;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateService;

#[AsCommand(
    name: 'wsc:variant:debug',
    description: 'Debug variant update - shows what would be changed'
)]
class DebugVariantCommand extends Command
{
    public function __construct(
        private readonly VariantUpdateService $variantUpdateService,
        private readonly TwigTemplateRenderer $templateRenderer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'product-number',
            null,
            InputOption::VALUE_REQUIRED,
            'Product number to debug'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $productNumber = $input->getOption('product-number');
        if (empty($productNumber)) {
            $io->error('--product-number is required');
            return Command::FAILURE;
        }

        $io->title("Debug: Variant Update for '{$productNumber}'");

        // Load parent product
        $parent = $this->variantUpdateService->loadParentProduct($productNumber, $context);

        if (!$parent) {
            $io->error("Product '{$productNumber}' not found");
            return Command::FAILURE;
        }

        $io->success("Parent product found: {$parent->getName()}");
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $parent->getId()],
                ['Product Number', $parent->getProductNumber()],
                ['Name', $parent->getName()],
            ]
        );

        // Load variants
        $variants = $this->variantUpdateService->loadVariants($parent->getId(), $context);

        if ($variants->count() === 0) {
            $io->warning('No variants found');
            return Command::SUCCESS;
        }

        $io->section("Found {$variants->count()} variant(s)");

        // Debug each variant
        foreach ($variants as $variant) {
            $options = $variant->getOptions() ? $variant->getOptions()->getElements() : [];

            $io->writeln('');
            $io->writeln("=== Variant: {$variant->getId()} ===");

            $io->table(
                ['Property', 'Current Value', 'New Value (would be)'],
                [
                    [
                        'Product Number',
                        $variant->getProductNumber(),
                        $this->templateRenderer->renderProductNumber($parent, $options)
                    ],
                    [
                        'Name',
                        $variant->getName(),
                        $this->templateRenderer->renderProductName($parent, $options)
                    ],
                ]
            );

            $io->writeln('Options:');
            foreach ($options as $option) {
                $io->writeln("  - {$option->getGroup()->getName()}: {$option->getName()}");
            }

            // Check if there would be a change
            $newName = $this->templateRenderer->renderProductName($parent, $options);
            $newNumber = $this->templateRenderer->renderProductNumber($parent, $options);

            if ($variant->getName() === $newName && $variant->getProductNumber() === $newNumber) {
                $io->warning('  → NO CHANGES would be made (already correct)');
            } else {
                $io->success('  → CHANGES would be made!');
            }
        }

        return Command::SUCCESS;
    }
}
