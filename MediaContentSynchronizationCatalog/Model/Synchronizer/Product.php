<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MediaContentSynchronizationCatalog\Model\Synchronizer;

use Magento\MediaContentApi\Api\Data\ContentIdentityInterfaceFactory;
use Magento\MediaContentApi\Api\UpdateContentAssetLinksInterface;
use Magento\MediaContentApi\Model\GetEntityContentsInterface;
use Magento\MediaContentSynchronizationApi\Api\SynchronizerInterface;
use Magento\MediaGallerySynchronizationApi\Api\SelectByBatchesGeneratorInterface;

/**
 * Synchronize product content with assets
 */
class Product implements SynchronizerInterface
{
    private const CONTENT_TYPE = 'catalog_product';
    private const TYPE = 'entityType';
    private const ENTITY_ID = 'entityId';
    private const FIELD = 'field';
    private const PRODUCT_TABLE = 'catalog_product_entity';
    private const PRODUCT_TABLE_ENTITY_ID = 'entity_id';

    /**
     * @var UpdateContentAssetLinksInterface
     */
    private $updateContentAssetLinks;

    /**
     * @var ContentIdentityInterfaceFactory
     */
    private $contentIdentityFactory;

    /**
     * @var GetEntityContentsInterface
     */
    private $getEntityContents;

    /**
     * @var array
     */
    private $fields;

    /**
     * @var SelectByBatchesGeneratorInterface
     */
    private $selectBatches;
    
    /**
     * @param ContentIdentityInterfaceFactory $contentIdentityFactory
     * @param GetEntityContentsInterface $getEntityContents
     * @param UpdateContentAssetLinksInterface $updateContentAssetLinks
     * @param SelectByBatchesGeneratorInterface $selectBatches
     * @param array $fields
     */
    public function __construct(
        ContentIdentityInterfaceFactory $contentIdentityFactory,
        GetEntityContentsInterface $getEntityContents,
        UpdateContentAssetLinksInterface $updateContentAssetLinks,
        SelectByBatchesGeneratorInterface $selectBatches,
        array $fields = []
    ) {
        $this->contentIdentityFactory = $contentIdentityFactory;
        $this->getEntityContents = $getEntityContents;
        $this->updateContentAssetLinks = $updateContentAssetLinks;
        $this->selectBatches = $selectBatches;
        $this->fields = $fields;
    }

    /**
     * @inheritdoc
     */
    public function execute(): void
    {
        foreach ($this->selectBatches->execute(self::PRODUCT_TABLE, [self::PRODUCT_TABLE_ENTITY_ID]) as $batch) {
            foreach ($batch as $item) {
                foreach ($this->fields as $field) {
                    $contentIdentity = $this->contentIdentityFactory->create(
                        [
                            self::TYPE => self::CONTENT_TYPE,
                            self::FIELD => $field,
                            self::ENTITY_ID => $item[self::PRODUCT_TABLE_ENTITY_ID]
                        ]
                    );
                    $this->updateContentAssetLinks->execute(
                        $contentIdentity,
                        implode(PHP_EOL, $this->getEntityContents->execute($contentIdentity))
                    );
                }
            }
        }
    }
}
