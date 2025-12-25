<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Controller\Administration;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use WSCPlugin\SWVariantUpdater\Message\UpdateVariantsMessage;
use WSCPlugin\SWVariantUpdater\Service\ProgressTracker;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateConfig;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateService;

#[Route(defaults: ['_routeScope' => ['api']])]
class VariantUpdateController extends AbstractController
{
    public function __construct(
        private readonly VariantUpdateService $variantUpdateService,
        private readonly MessageBusInterface $messageBus,
        private readonly ProgressTracker $progressTracker,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Trigger variant update via message queue.
     */
    #[Route(
        path: '/api/_action/wsc-variant-updater/update',
        name: 'api.action.wsc-variant-updater.update',
        methods: ['POST']
    )]
    public function triggerUpdate(Request $request, Context $context): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $this->logger->info('Admin variant update triggered', [
                'raw_content' => $request->getContent(),
                'decoded_data' => $data,
            ]);

            // Validate input
            if (empty($data['productNumbers']) || !\is_array($data['productNumbers'])) {
                $this->logger->error('Invalid product numbers', [
                    'data' => $data,
                ]);
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Product numbers are required',
                ], 400);
            }

            $productNumbers = $data['productNumbers'];
            $batchId = 'batch_' . Uuid::randomHex();

            // Create config with SystemConfig fallback
            $config = VariantUpdateConfig::fromRequestWithDefaults(
                $data,
                $this->systemConfigService
            );

            // Create and dispatch message
            $message = new UpdateVariantsMessage(
                $productNumbers,
                $config,
                $batchId,
                $context
            );

            $this->messageBus->dispatch($message);

            $this->logger->info('Variant update triggered from admin', [
                'batch_id' => $batchId,
                'product_count' => \count($productNumbers),
                'user_id' => $context->getSource()->getUserId() ?? 'unknown',
            ]);

            return new JsonResponse([
                'success' => true,
                'batchId' => $batchId,
                'productCount' => \count($productNumbers),
                'message' => sprintf('Update queued for %d products', \count($productNumbers)),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to trigger variant update from admin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get progress for a batch operation.
     */
    #[Route(
        path: '/api/_action/wsc-variant-updater/progress/{batchId}',
        name: 'api.action.wsc-variant-updater.progress',
        methods: ['GET']
    )]
    public function getProgress(string $batchId, Context $context): JsonResponse
    {
        try {
            $progress = $this->progressTracker->getProgressByBatchId($batchId, $context);

            if (!$progress) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Batch not found',
                ], 404);
            }

            $percentage = $this->progressTracker->getProgressPercentage($batchId, $context);

            return new JsonResponse([
                'success' => true,
                'batchId' => $batchId,
                'totalProducts' => $progress->getTotalProducts(),
                'processedProducts' => $progress->getProcessedProducts(),
                'failedProducts' => $progress->getFailedProducts(),
                'currentBatchSize' => $progress->getCurrentBatchSize(),
                'status' => $progress->getStatus(),
                'percentage' => round($percentage, 2),
                'startedAt' => $progress->getStartedAt()?->format('c'),
                'finishedAt' => $progress->getFinishedAt()?->format('c'),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get progress', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system config for the plugin.
     */
    #[Route(
        path: '/api/_action/wsc-variant-updater/config',
        name: 'api.action.wsc-variant-updater.config',
        methods: ['GET']
    )]
    public function getConfig(): JsonResponse
    {
        try {
            $config = [
                'nameTemplate' => $this->systemConfigService->getString('WSCPluginSWVariantUpdater.config.nameTemplate'),
                'numberTemplate' => $this->systemConfigService->getString('WSCPluginSWVariantUpdater.config.numberTemplate'),
                'nameOnly' => $this->systemConfigService->getBool('WSCPluginSWVariantUpdater.config.nameOnly'),
                'numberOnly' => $this->systemConfigService->getBool('WSCPluginSWVariantUpdater.config.numberOnly'),
                'batchSize' => $this->systemConfigService->getInt('WSCPluginSWVariantUpdater.config.batchSize'),
            ];

            return new JsonResponse([
                'success' => true,
                'config' => $config,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
