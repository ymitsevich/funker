<?php

namespace Ymitsevich\Funker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class IdMutableEntityManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function persist($entity): void
    {
        $this->enableManualSetId($entity);

        $classMetaData = $this->entityManager->getClassMetadata(get_class($entity));
        if ($classMetaData->isEmbeddedClass) {
            return;
        }

        $this->entityManager->persist($entity);
    }

    public function flush(): void
    {
        $entities = $this->entityManager->getUnitOfWork()->getScheduledEntityInsertions();
        $this->entityManager->flush();

        foreach ($entities as $entity) {
            $this->disableManualSetId($entity);
        }
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }

    private function enableManualSetId($entity): void
    {
        $metadata = $this->entityManager->getClassMetaData($entity::class);
        $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());
    }

    private function disableManualSetId($entity): void
    {
        $metadata = $this->entityManager->getClassMetaData($entity::class);
        $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);
        $metadata->setIdGenerator(new IdentityGenerator());
    }
}