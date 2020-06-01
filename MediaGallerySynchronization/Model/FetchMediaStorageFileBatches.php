<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MediaGallerySynchronization\Model;

use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\MediaGalleryApi\Api\IsPathBlacklistedInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Fetch files from media storage in batches
 */
class FetchMediaStorageFileBatches
{
    private const IMAGE_FILE_NAME_PATTERN = '#\.(jpg|jpeg|gif|png)$# i';
    
    /**
     * @var GetAssetsIterator
     */
    private $getAssetsIterator;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var IsPathBlacklistedInterface
     */
    private $isPathBlacklisted;

    /**
     * @var File
     */
    private $driver;
    
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var int
     */
    private $batchSize;
    
    /**
     * @param LoggerInterface $log
     * @param IsPathBlacklistedInterface $isPathBlacklisted
     * @param Filesystem $filesystem
     * @param GetAssetsIterator $assetsIterator
     * @param File $driver
     * @param int $batchSize
     */
    public function __construct(
        LoggerInterface $log,
        IsPathBlacklistedInterface $isPathBlacklisted,
        Filesystem $filesystem,
        GetAssetsIterator $assetsIterator,
        File $driver,
        int $batchSize
    ) {
        $this->log = $log;
        $this->isPathBlacklisted = $isPathBlacklisted;
        $this->getAssetsIterator = $assetsIterator;
        $this->filesystem = $filesystem;
        $this->driver = $driver;
        $this->batchSize = $batchSize;
    }

    /**
     * Return files from files system by provided size of batch
     */
    public function execute(): \Traversable
    {
        $i = 0;
        $batch = [];
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        
        /** @var \SplFileInfo $file */
        foreach ($this->getAssetsIterator->execute($mediaDirectory->getAbsolutePath()) as $file) {
            if (!$this->isApplicable($file->getPathName())) {
                continue;
            }

            $batch[] = $file;
            if (++$i == $this->batchSize) {
                yield $batch;
                $i = 0;
                $batch = [];
            }
        }
        if (count($batch) > 0) {
            yield $batch;
        }
    }
    
    /**
     * Get correct path for media asset
     *
     * @param string $file
     * @return string
     * @throws ValidatorException
     */
    private function getRelativePath(string $file): string
    {
        $path = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getRelativePath($file);

        if ($this->driver->getParentDirectory($path) === '.') {
            $path = '/' . $path;
        }

        return $path;
    }
    
    /**
     * Can synchronization be applied to asset with provided path
     *
     * @param string $path
     * @return bool
     */
    private function isApplicable(string $path): bool
    {
        try {
            $relativePath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getRelativePath($path);
            return $relativePath
                && !$this->isPathBlacklisted->execute($relativePath)
                && preg_match(self::IMAGE_FILE_NAME_PATTERN, $path);
        } catch (\Exception $exception) {
            $this->log->critical($exception);
            return false;
        }
    }
}