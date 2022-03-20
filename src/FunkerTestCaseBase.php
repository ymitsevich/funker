<?php

namespace Ymitsevich\Funker;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FunkerTestCaseBase extends WebTestCase
{
    private const DIR_DATA = 'data';

    protected ?EntityManagerInterface $entityManager;
    private YamlManager $yamlManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $kernel = $this->client->getKernel();
        if ('test' !== self::$kernel->getEnvironment()) {
            throw new LogicException('Tests cases with fresh database must be executed in the test environment');
        }

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->yamlManager = new YamlManager($this->entityManager, $this->getDataFilename());

        $this->yamlManager->applyFixtures();

        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $purger = new ORMPurger($this->entityManager);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
        $purger->purge();

        $this->entityManager->close();
        $this->entityManager = null;
        gc_collect_cycles();
    }

    protected function getJsonDecodedResponse(): array
    {
        $response = $this->client->getResponse();
        $content = $response->getContent();
        if ($content === '') {
            return [];
        }

        $this->assertJson($content);

        return json_decode($content, true);
    }

    protected function getResponseCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    protected function assertResponseStatusCode(int $statusCode): void
    {
        $this->assertEquals($statusCode, $this->getResponseCode());
    }

    protected function assertContentEqualsToSnapshot(bool $strict = true): void
    {
        $resultData = $this->getJsonDecodedResponse();

        $snapshot = $this->yamlManager->getYamlSnapshot();

        if ($strict) {
            $this->assertSame($snapshot, $resultData);

            return;
        }

        $this->assertEquals($snapshot, $resultData);
    }

    protected function getDataFilename(?string $filename = null): string
    {
        $filename = $filename ?? "{$this->getName()}.yaml";

        $testReflection = new ReflectionClass($this);
        $dirName = dirname($testReflection->getFilename());

        return $dirName . DIRECTORY_SEPARATOR . self::DIR_DATA . DIRECTORY_SEPARATOR . $filename;
    }
}
