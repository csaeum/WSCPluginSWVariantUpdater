<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Service;

/**
 * Configuration object for variant updates.
 */
class VariantUpdateConfig
{
    public function __construct(
        public readonly bool $dryRun = false,
        public readonly bool $nameOnly = false,
        public readonly bool $numberOnly = false
    ) {
    }

    /**
     * Create config from command options.
     *
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            dryRun: (bool) ($options['dry-run'] ?? false),
            nameOnly: (bool) ($options['name-only'] ?? false),
            numberOnly: (bool) ($options['number-only'] ?? false)
        );
    }
}
