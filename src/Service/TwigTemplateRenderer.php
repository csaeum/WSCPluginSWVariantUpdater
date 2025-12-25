<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Service;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * Renders Twig templates for product names and numbers.
 */
class TwigTemplateRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * Render product name using configured Twig template.
     *
     * @param ProductEntity $parentProduct Parent product entity
     * @param array<\Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionEntity> $options Array of option entities
     *
     * @return string Rendered product name
     */
    public function renderProductName(ProductEntity $parentProduct, array $options): string
    {
        $template = $this->systemConfigService->getString('WSCPluginSWVariantUpdater.config.nameTemplate');

        // Fallback to default logic if no template configured
        if (empty($template)) {
            return $this->getDefaultProductName($parentProduct, $options);
        }

        try {
            return $this->renderTemplate($template, [
                'parentProduct' => $parentProduct,
                'options' => $options,
            ]);
        } catch (TwigError $e) {
            // Log error and fallback to default
            return $this->getDefaultProductName($parentProduct, $options);
        }
    }

    /**
     * Render product number using configured Twig template.
     *
     * @param ProductEntity $parentProduct Parent product entity
     * @param array<\Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionEntity> $options Array of option entities
     *
     * @return string Rendered product number
     */
    public function renderProductNumber(ProductEntity $parentProduct, array $options): string
    {
        $template = $this->systemConfigService->getString('WSCPluginSWVariantUpdater.config.numberTemplate');

        // Fallback to default logic if no template configured
        if (empty($template)) {
            return $this->getDefaultProductNumber($parentProduct, $options);
        }

        try {
            return $this->renderTemplate($template, [
                'parentProduct' => $parentProduct,
                'options' => $options,
            ]);
        } catch (TwigError $e) {
            // Log error and fallback to default
            return $this->getDefaultProductNumber($parentProduct, $options);
        }
    }

    /**
     * Render a Twig template string.
     *
     * @param string $template Template string
     * @param array<string, mixed> $context Template context
     *
     * @return string Rendered output
     *
     * @throws TwigError
     */
    private function renderTemplate(string $template, array $context): string
    {
        $twigTemplate = $this->twig->createTemplate($template);

        return trim($twigTemplate->render($context));
    }

    /**
     * Get default product name (fallback).
     */
    private function getDefaultProductName(ProductEntity $parentProduct, array $options): string
    {
        $optionNames = array_map(fn ($option) => $option->getTranslated()['name'] ?? $option->getName(), $options);

        $parentName = $parentProduct->getTranslated()['name'] ?? $parentProduct->getName();

        return trim($parentName . ' ' . implode(' ', $optionNames));
    }

    /**
     * Get default product number (fallback).
     */
    private function getDefaultProductNumber(ProductEntity $parentProduct, array $options): string
    {
        if (empty($options)) {
            return $parentProduct->getProductNumber();
        }

        $optionParts = [];
        foreach ($options as $option) {
            $normalized = $this->normalizeString(mb_strtolower($option->getName()));
            $optionParts[] = $normalized;
        }

        return $parentProduct->getProductNumber() . '-' . implode('-', $optionParts);
    }

    /**
     * Normalize string for product numbers.
     */
    private function normalizeString(string $string): string
    {
        // Try transliterator first
        if (\function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
            if ($transliterated !== false) {
                $string = $transliterated;
            }
        } else {
            // Fallback: German umlauts
            $replacements = [
                'ä' => 'ae',
                'ö' => 'oe',
                'ü' => 'ue',
                'ß' => 'ss',
                'Ä' => 'ae',
                'Ö' => 'oe',
                'Ü' => 'ue',
            ];
            $string = str_replace(array_keys($replacements), array_values($replacements), $string);
        }

        // Replace spaces with hyphens
        $string = str_replace(' ', '-', $string);

        return $string;
    }
}
