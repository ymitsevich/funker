<?php

namespace Ymitsevich\Funker;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\Sorter\TopologicalSorter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * extended ORMPurger to add reseting table's autoincrement while in DELETE mode
 */
class IncrementResetPurger extends ORMPurger
{
    protected int $purgeMode = self::PURGE_MODE_DELETE;

    public function __construct(private ?EntityManagerInterface $em = null, private array $excluded = [])
    {
        $this->em = $em;
        $this->excluded = $excluded;
    }

    public function purge()
    {
        $classes = [];

        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || (isset($metadata->isEmbeddedClass) && $metadata->isEmbeddedClass)) {
                continue;
            }

            $classes[] = $metadata;
        }

        $commitOrder = $this->getCommitOrder($this->em, $classes);

        $platform = $this->em->getConnection()->getDatabasePlatform();

        $orderedTables = $this->getAssociationTables($commitOrder, $platform);

        for ($i = count($commitOrder) - 1; $i >= 0; --$i) {
            $class = $commitOrder[$i];

            if (
                (isset($class->isEmbeddedClass) && $class->isEmbeddedClass) ||
                $class->isMappedSuperclass ||
                ($class->isInheritanceTypeSingleTable() && $class->name !== $class->rootEntityName)
            ) {
                continue;
            }

            $orderedTables[] = $this->getTableName($class, $platform);
        }

        $connection = $this->em->getConnection();
        $filterExpr = method_exists(
            $connection->getConfiguration(),
            'getFilterSchemaAssetsExpression'
        ) ? $connection->getConfiguration()->getFilterSchemaAssetsExpression() : null;
        $emptyFilterExpression = empty($filterExpr);

        $schemaAssetsFilter = method_exists(
            $connection->getConfiguration(),
            'getSchemaAssetsFilter'
        ) ? $connection->getConfiguration()->getSchemaAssetsFilter() : null;

        foreach ($orderedTables as $tbl) {
            if (!$emptyFilterExpression && !preg_match($filterExpr, $tbl)) {
                continue;
            }

            if (array_search($tbl, $this->excluded) !== false) {
                continue;
            }

            if (is_callable($schemaAssetsFilter) && !$schemaAssetsFilter($tbl)) {
                continue;
            }

            if ($this->purgeMode === self::PURGE_MODE_DELETE) {
                $connection->executeStatement($this->getDeleteFromTableQuery($tbl, $platform));
                $connection->executeStatement($this->getAutoincrementResetQuery($tbl, $platform));
            } else {
                $connection->executeStatement($platform->getTruncateTableSQL($tbl, true));
            }
        }
    }

    private function getCommitOrder(EntityManagerInterface $em, array $classes): array
    {
        $sorter = new TopologicalSorter();

        foreach ($classes as $class) {
            if (!$sorter->hasNode($class->name)) {
                $sorter->addNode($class->name, $class);
            }

            foreach ($class->parentClasses as $parentClass) {
                $parentClass = $em->getClassMetadata($parentClass);
                $parentClassName = $parentClass->getName();

                if (!$sorter->hasNode($parentClassName)) {
                    $sorter->addNode($parentClassName, $parentClass);
                }

                $sorter->addDependency($class->name, $parentClassName);
            }

            foreach ($class->associationMappings as $assoc) {
                if (!$assoc['isOwningSide']) {
                    continue;
                }

                $targetClass = $em->getClassMetadata($assoc['targetEntity']);
                assert($targetClass instanceof ClassMetadata);
                $targetClassName = $targetClass->getName();

                if (!$sorter->hasNode($targetClassName)) {
                    $sorter->addNode($targetClassName, $targetClass);
                }

                $sorter->addDependency($targetClassName, $class->name);

                foreach ($targetClass->parentClasses as $parentClass) {
                    $parentClass = $em->getClassMetadata($parentClass);
                    $parentClassName = $parentClass->getName();

                    if (!$sorter->hasNode($parentClassName)) {
                        $sorter->addNode($parentClassName, $parentClass);
                    }

                    $sorter->addDependency($parentClassName, $class->name);
                }
            }
        }

        return array_reverse($sorter->sort());
    }

    private function getAssociationTables(array $classes, AbstractPlatform $platform): array
    {
        $associationTables = [];

        foreach ($classes as $class) {
            foreach ($class->associationMappings as $assoc) {
                if (!$assoc['isOwningSide'] || $assoc['type'] !== ClassMetadata::MANY_TO_MANY) {
                    continue;
                }

                $associationTables[] = $this->getJoinTableName($assoc, $class, $platform);
            }
        }

        return $associationTables;
    }

    private function getTableName(ClassMetadata $class, AbstractPlatform $platform): string
    {
        if (isset($class->table['schema']) && !method_exists($class, 'getSchemaName')) {
            return $class->table['schema'] . '.' .
                $this->em->getConfiguration()
                    ->getQuoteStrategy()
                    ->getTableName($class, $platform);
        }

        return $this->em->getConfiguration()->getQuoteStrategy()->getTableName($class, $platform);
    }

    private function getJoinTableName(
        array $assoc,
        ClassMetadata $class,
        AbstractPlatform $platform
    ): string {
        if (isset($assoc['joinTable']['schema']) && !method_exists($class, 'getSchemaName')) {
            return $assoc['joinTable']['schema'] . '.' .
                $this->em->getConfiguration()
                    ->getQuoteStrategy()
                    ->getJoinTableName($assoc, $class, $platform);
        }

        return $this->em->getConfiguration()->getQuoteStrategy()->getJoinTableName($assoc, $class, $platform);
    }

    private function getDeleteFromTableQuery(string $tableName, AbstractPlatform $platform): string
    {
        $tableNameSubstring = (new Identifier($tableName))->getQuotedName($platform);

        return "DELETE FROM $tableNameSubstring";
    }

    private function getAutoincrementResetQuery(string $tableName, AbstractPlatform $platform): string
    {
        $tableNameSubstring = (new Identifier($tableName))->getQuotedName($platform);

        return "ALTER TABLE $tableNameSubstring AUTO_INCREMENT = 1";
    }
}