<?php

namespace Ymitsevich\Funker;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Yaml;

class FunkerTestCaseBase extends WebTestCase
{
    private const RESPONSES_DIR_DATA = 'responses';

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

        $this->yamlManager = new YamlManager(
            new IdMutableEntityManager($this->entityManager),
            $this->getFixtureFilename()
        );

        try {
            $this->yamlManager->applyFixtures();
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->purgeDbData();
        }

        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->purgeDbData();

        $this->clean();
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

        $snapshot = $this->getResponseSnapshot();

        if ($strict) {
            $this->assertSame($snapshot, $resultData);

            return;
        }

        $this->assertEquals($snapshot, $resultData);
    }

    protected function getResponseSnapshot(): array
    {
        return Yaml::parse($this->getResponseContent());
    }

    protected function getResponseContent(): string
    {
        return file_get_contents($this->getResponseFilename());
    }

    protected function getResponseFilename(?string $filename = null): string
    {
        $filename = $filename ?? "{$this->getName()}.yaml";

        $testReflection = new ReflectionClass($this);
        $dirName = dirname($testReflection->getFilename());

        return $dirName . DIRECTORY_SEPARATOR . self::RESPONSES_DIR_DATA . DIRECTORY_SEPARATOR . $filename;
    }

    protected function getFixtureFilename(?string $filename = null): string
    {
        $testReflection = new ReflectionClass($this);
        $dirName = dirname($testReflection->getFilename());
        $filename = $filename ?? "{$testReflection->getShortName()}.yaml";

        return $dirName . DIRECTORY_SEPARATOR . $filename;
    }

    private function purgeDbData(): void
    {
        $purger = new IncrementResetPurger($this->entityManager);
        $purger->setPurgeMode(IncrementResetPurger::PURGE_MODE_DELETE);
        $purger->purge();
    }

    private function clean(): void
    {
        $this->entityManager->close();
        $this->entityManager = null;
        gc_collect_cycles();
    }
}
