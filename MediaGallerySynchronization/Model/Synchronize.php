<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MediaGallerySynchronization\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\MediaGallerySynchronizationApi\Api\SynchronizeInterface;
use Magento\MediaGallerySynchronizationApi\Api\SynchronizeFilesInterface;
use Magento\MediaGallerySynchronizationApi\Model\SynchronizerPool;
use Psr\Log\LoggerInterface;
use Magento\MediaGallerySynchronization\Model\AssetsBatchGenerator;

/**
 * Synchronize media storage and media assets database records
 */
class Synchronize implements SynchronizeInterface
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var SynchronizerPool
     */
    private $synchronizerPool;

    /**
     * @var AssetsBatchGenerator
     */
    private $batchGenerator;

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var ResolveNonExistedAssets
     */
    private $resolveNonExistedAssets;
    
    /**
     * @param ResolveNonExistedAssets $resolveNonExistedAssets
     * @param LoggerInterface $log
     * @param SynchronizerPool $synchronizerPool
     * @param AssetsBatchGenerator $batchGenerator
     * @param int $batchSize
     */
    public function __construct(
        ResolveNonExistedAssets $resolveNonExistedAssets,
        LoggerInterface $log,
        SynchronizerPool $synchronizerPool,
        AssetsBatchGenerator $batchGenerator,
        int $batchSize
    ) {
        $this->resolveNonExistedAssets = $resolveNonExistedAssets;
        $this->log = $log;
        $this->synchronizerPool = $synchronizerPool;
        $this->batchGenerator = $batchGenerator;
        $this->batchSize = $batchSize;
    }

    /**
     * @inheritdoc
     */
    public function execute(): void
    {
        $failed = [];

        foreach ($this->synchronizerPool->get() as $name => $synchronizer) {
            if ($synchronizer instanceof SynchronizeFilesInterface) {
                foreach ($this->batchGenerator->getItems($this->batchSize) as $batch) {
                    try {
                        $synchronizer->execute($batch);
                        $this->resolveNonExistedAssets->execute($batch);
                    } catch (\Exception $exception) {
                        $this->log->critical($exception);
                        $failed[] = $name;
                    }
                }
            } else {
                throw new LocalizedException(__('Synchronizer must implement SynchronizeFilesInterface'));
            }
        }
        if (!empty($failed)) {
            throw new LocalizedException(
                __(
                    'Failed to execute the following synchronizers: %synchronizers',
                    [
                        'synchronizers' => implode(', ', $failed)
                    ]
                )
            );
        }
    }
}
