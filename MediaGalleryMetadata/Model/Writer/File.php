<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MediaGalleryMetadata\Model\Writer;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\MediaGalleryMetadata\Model\File as FileDataObject;
use Magento\MediaGalleryMetadata\Model\Segment;
use Magento\MediaGalleryMetadata\Model\SegmentNames;

/**
 * File segments reader
 */
class File
{
    private const MARKER_IMAGE_FILE_START = "\xD8";
    private const MARKER_IMAGE_PREFIX = "\xFF";
    private const MARKER_IMAGE_END = "\xD9";

    /**
     * @var DriverInterface
     */
    private $driver;

    /**
     * @var SegmentNames
     */
    private $segmentNames;

    /**
     * File constructor.
     * @param DriverInterface $driver
     * @param SegmentNames $segmentNames
     */
    public function __construct(
        DriverInterface $driver,
        SegmentNames $segmentNames
    ) {
        $this->driver = $driver;
        $this->segmentNames = $segmentNames;
    }

    /**
     * @param FileDataObject $file
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function execute(FileDataObject $file): void
    {
        foreach ($file->getSegments() as $segment) {
            if (strlen($segment->getData()) > 0xfffd) {
                throw new LocalizedException(__('A Header is too large to fit in the segment!'));
            }
        }

        $resource = $this->driver->fileOpen($file->getPath(), 'wb');

        $this->driver->fileWrite($resource, self::MARKER_IMAGE_PREFIX . self::MARKER_IMAGE_FILE_START);
        $this->writeSegments($resource, $file->getSegments());
        $this->driver->fileWrite($resource, $file->getCompressedImage());
        $this->driver->fileWrite($resource, self::MARKER_IMAGE_PREFIX . self::MARKER_IMAGE_END);
        $this->driver->fileClose($resource);
    }

    /**
     * @param resource $resource
     * @param Segment[] $segments
     */
    private function writeSegments($resource, array $segments): void
    {
        foreach ($segments as $segment) {
            $this->driver->fileWrite(
                $resource,
                self::MARKER_IMAGE_PREFIX . chr($this->segmentNames->getSegmentType($segment->getName()))
            );
            $this->driver->fileWrite($resource, pack("n", strlen($segment->getData()) + 2));
            $this->driver->fileWrite($resource, $segment->getData());
        }
    }
}