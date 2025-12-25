<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Configuration object for variant updates.
 */
class VariantUpdateConfig
{
    public function __construct(
        public readonly bool $dryRun = false,
        public readonly bool $nameOnly = false,
        public readonly bool $numberOnly = false,
        public readonly ?string $nameTemplate = null,
        public readonly ?string $numberTemplate = null,
        public readonly ?int $batchSize = null
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

    /**
     * Create config from request with SystemConfig fallback.
     * Fallback chain: Request data > SystemConfig > Hardcoded defaults
     *
     * @param array<string, mixed> $requestData Request parameters (CLI options or API request)
     * @param SystemConfigService|null $systemConfig SystemConfig service for fallback values
     */
    public static function fromRequestWithDefaults(
        array $requestData,
        ?SystemConfigService $systemConfig = null
    ): self {
        return new self(
            dryRun: $requestData['dry-run'] ?? $requestData['dryRun'] ?? false,
            nameOnly: $requestData['name-only'] ?? $requestData['nameOnly']
                ?? $systemConfig?->getBool('WSCPluginSWVariantUpdater.config.nameOnly')
                ?? false,
            numberOnly: $requestData['number-only'] ?? $requestData['numberOnly']
                ?? $systemConfig?->getBool('WSCPluginSWVariantUpdater.config.numberOnly')
                ?? false,
            nameTemplate: $requestData['name-template'] ?? $requestData['nameTemplate']
                ?? $systemConfig?->getString('WSCPluginSWVariantUpdater.config.nameTemplate')
                ?? null,
            numberTemplate: $requestData['number-template'] ?? $requestData['numberTemplate']
                ?? $systemConfig?->getString('WSCPluginSWVariantUpdater.config.numberTemplate')
                ?? null,
            batchSize: $requestData['batch-size'] ?? $requestData['batchSize']
                ?? $systemConfig?->getInt('WSCPluginSWVariantUpdater.config.batchSize')
                ?? null
        );
    }
}
