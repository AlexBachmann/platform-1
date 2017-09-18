<?php

namespace Shopware\Media\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Media\Factory\MediaBasicFactory;
use Shopware\Media\Struct\MediaBasicCollection;
use Shopware\Media\Struct\MediaBasicStruct;

class MediaBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var MediaBasicFactory
     */
    private $factory;

    public function __construct(
        MediaBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): MediaBasicCollection
    {
        if (empty($uuids)) {
            return new MediaBasicCollection();
        }

        $medias = $this->read($uuids, $context);

        return $medias;
    }

    private function read(array $uuids, TranslationContext $context): MediaBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('media.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new MediaBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new MediaBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
