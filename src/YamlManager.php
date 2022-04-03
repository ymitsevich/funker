<?php

namespace Ymitsevich\Funker;

use Doctrine\ORM\EntityManagerInterface;
use Nelmio\Alice\Loader\NativeLoader;
use Symfony\Component\Yaml\Yaml;

class YamlManager
{
    private static ?array $data = null;

    public function __construct(private IdMutableEntityManager $em, private string $dataFileName)
    {
    }

    public function getYamlSnapshot(): array
    {
        return Yaml::parse($this->getFixtureContent());
    }

    public function applyFixtures(): void
    {
        $loader = new WithReflectionLoader();

        $fixture = Yaml::parse($this->getFixtureContent());
        $objectSet = $loader->loadData($fixture);
        foreach ($objectSet->getObjects() as $object) {
            $this->em->persist($object);
        }
        $this->em->flush();
    }

    private function getFixtureContent(): string
    {
        if (!static::$data || !array_key_exists($this->dataFileName, static::$data)) {
            $dataSrc = file_get_contents($this->dataFileName);
            static::$data[$this->dataFileName] = $dataSrc;
        }

        return static::$data[$this->dataFileName];
    }
}
