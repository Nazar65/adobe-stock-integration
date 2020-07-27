<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MediaGalleryMetadata\Model\File;

use Magento\MediaGalleryMetadataApi\Api\Data\MetadataInterface;
use Magento\MediaGalleryMetadataApi\Api\Data\MetadataInterfaceFactory;
use Magento\Framework\Exception\ValidatorException;
use Magento\MediaGalleryMetadataApi\Model\FileInterfaceFactory;
use Magento\MediaGalleryMetadataApi\Model\FileInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\MediaGalleryMetadataApi\Model\ReadFileInterface;
use Magento\MediaGalleryMetadataApi\Model\ReadMetadataInterface;

/**
 * Extract Metadata from asset file by given extractors
 */
class ExtractMetadata implements ExtractMetadataInterface
{

    /**
     * @var MetadataInterfaceFactory
     */
    private $metadataFactory;

    /**
     * @var array
     */
    private $segmentReaders;

    /**
     * @var ReadFileInterface
     */
    private $fileReader;

    /**
     * @var FileInterfaceFactory
     */
    private $fileFactory;

    /**
     * @param FileInterfaceFactory $fileFactory
     * @param MetadataInterfaceFactory $metadataFactory
     * @param ReadFileInterface $fileReader
     * @param array $segmentReaders
     */
    public function __construct(
        FileInterfaceFactory $fileFactory,
        MetadataInterfaceFactory $metadataFactory,
        ReadFileInterface $fileReader,
        array $segmentReaders
    ) {
        $this->fileFactory = $fileFactory;
        $this->metadataFactory = $metadataFactory;
        $this->fileReader = $fileReader;
        $this->segmentReaders = $segmentReaders;
    }

    /**
     * @inheritdoc
     */
    public function execute(string $path): MetadataInterface
    {
        try {
            return $this->extractMetadata($path);
        } catch (\Exception $exception) {
            return $this->metadataFactory->create();
        }
    }

    /**
     * Extract metadata from file
     *
     * @param string $path
     * @return MetadataInterface
     */
    private function extractMetadata(string $path): MetadataInterface
    {
        try {
            $file = $this->fileReader->execute($path);
        } catch (\Exception $exception) {
            throw new LocalizedException(
                __('Could not parse the image file for metadata: %path', ['path' => $path])
            );
        }

        return $this->readSegments($file);
    }

    /**
     * Read  file segments by given segmentReader
     *
     * @param FileInterface $file
     */
    private function readSegments(FileInterface $file): MetadataInterface
    {
        $title = null;
        $description = null;
        $keywords = [];
        
        foreach ($this->segmentReaders as $segmentReader) {
            if (!$segmentReader instanceof ReadMetadataInterface) {
                throw new \InvalidArgumentException(
                    __(get_class($segmentReader). ' must implement ' . ReadMetadataInterface::class)
                );
            }

            $data = $segmentReader->execute($file);
            $title = !empty($data->getTitle()) ? $data->getTitle() : $title;
            $description = !empty($data->getDescription()) ? $data->getDescription() : $description;
            $keywords =  $keywords + $data->getKeywords();
        }
        
        return $this->metadataFactory->create([
            'title' => $title,
            'description' => $description,
            'keywords' => empty($keywords) ? null : $keywords
        ]);
    }
}
