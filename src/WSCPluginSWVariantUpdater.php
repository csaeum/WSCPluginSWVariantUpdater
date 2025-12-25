<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\Process\Process;

class WSCPluginSWVariantUpdater extends Plugin
{
    /**
     * Wird nach der Installation ausgeführt.
     */
    public function postInstall(InstallContext $installContext): void
    {
        parent::postInstall($installContext);
        $this->buildAdministration();
    }

    /**
     * Wird nach dem Update ausgeführt.
     */
    public function postUpdate(UpdateContext $updateContext): void
    {
        parent::postUpdate($updateContext);
        $this->buildAdministration();
    }

    /**
     * Baut die Administration-Assets neu.
     */
    private function buildAdministration(): void
    {
        // Prüfe ob wir im CLI-Kontext sind
        if (!\defined('STDIN')) {
            // Im Web-Request können wir keinen Build triggern
            return;
        }

        $projectDir = $this->container->getParameter('kernel.project_dir');

        // Führe den Build-Prozess aus
        $process = new Process(
            ['bin/build-administration.sh'],
            $projectDir,
            null,
            null,
            300 // 5 Minuten Timeout
        );

        try {
            $process->run();
        } catch (\Exception $e) {
            // Fehler beim Build ignorieren (z.B. wenn Script nicht existiert)
            // Das Plugin sollte trotzdem funktionieren
        }
    }
}
