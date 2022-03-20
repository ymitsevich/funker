<?php

namespace Ymitsevich\Funker;

use Doctrine\ORM\EntityManagerInterface;
use Nelmio\Alice\Loader\NativeLoader;
use Symfony\Component\Yaml\Yaml;

class YamlManager
{
    private const KEY_FIXTURES = 0;
    private const KEY_SNAPSHOT = 1;

    private static ?array $data = null;

    public function __construct(private EntityManagerInterface $em, private string $dataFileName)
    {
    }

    public function getYamlSnapshot(): array
    {
        return Yaml::parse($this->getContent()[self::KEY_SNAPSHOT]);
    }

    public function applyFixtures(): void
    {
        $loader = new NativeLoader();

        $fixture = Yaml::parse($this->getContent()[self::KEY_FIXTURES]);
        $objectSet = $loader->loadData($fixture);
        foreach ($objectSet->getObjects() as $object) {
            $this->em->persist($object);
        }
        $this->em->flush();
    }

    private function getContent(): array
    {
        if (!static::$data || !array_key_exists($this->dataFileName, static::$data)) {
            $combinedDataSrc = file_get_contents($this->dataFileName);
            static::$data[$this->dataFileName] = explode('---', $combinedDataSrc);
        }

        return static::$data[$this->dataFileName];
    }
}
