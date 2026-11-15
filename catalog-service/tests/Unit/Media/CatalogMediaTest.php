<?php

namespace App\Tests\Unit\Media;

use App\Media\Infrastructure\CatalogPictureResponseFactory;
use App\Search\Product\Infrastructure\CatalogResponseFactory;
use App\DataFixtures\Media\FixtureMediaLibrary;
use App\DataFixtures\Media\FixtureMediaImporter;
use App\Entity\CatalogMedia;
use App\Media\CatalogMediaVariant;
use App\Repository\CatalogMediaRepository;
use App\Media\Infrastructure\CatalogMediaStorage;
use App\DataFixtures\CatalogMediaFixtures;
use App\DataFixtures\ProductSnapshotFixtures;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Persistence\ObjectManager;
use App\Search\Product\Application\Dto\Document\ProductSearchPicture;
use App\DataFixtures\AppFixtures;
use App\Controller\Api\CatalogMediaController;
use App\Entity\CatalogElements;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class CatalogMediaTest extends TestCase
{
    private string $directory;
    private string $key = 'catalog/demo/v1/office-chair/0123456789abcdef/main.webp';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/catalog-media-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $file = function (string $name, string $type): array {
            file_put_contents($this->directory.'/'.$name, 'test content '.$name);
            return ['path' => $name, 'key' => dirname($this->key).'/'.$name, 'sha256' => hash_file('sha256', $this->directory.'/'.$name), 'contentType' => $type];
        };
        $manifest = ['schemaVersion' => 1, 'assets' => [[
            'assetId' => 'office-chair', 'categorySlugs' => ['catalog-home-garden-furniture-office'],
            'productType' => 'Office Chair', 'alt' => 'Office chair',
            'creator' => 'Photographer', 'sourceUrl' => 'https://example.com/photo',
            'license' => 'CC BY 4.0', 'licenseUrl' => 'https://creativecommons.org/licenses/by/4.0/',
            'attribution' => 'Photo by Photographer, CC BY 4.0; resized.',
            'source' => $file('source.jpg', 'image/jpeg'),
            'variants' => ['thumb' => $file('thumb.webp', 'image/webp'), 'card' => $file('card.webp', 'image/webp'), 'main' => $file('main.webp', 'image/webp')],
        ]]];
        file_put_contents($this->directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    public function testSharedSelectionAndUrlsDoNotNeedStorage(): void
    {
        $library = new FixtureMediaLibrary($this->directory);
        $first = $library->forCategory('catalog-home-garden-furniture-office', 1);
        self::assertSame($first, $library->forCategory('catalog-home-garden-furniture-office', 1000000));
        self::assertSame($first, $library->forSection('catalog-home-garden'));
        $picture = (new CatalogPictureResponseFactory('https://shop.example/api/v1/catalog/media/'))->fromEntity($this->media());
        self::assertSame('https://shop.example/api/v1/catalog/media/'.$this->key, $picture->fullUrl);
        self::assertStringEndsWith('/thumb.webp', $picture->thumbnailUrl);
        self::assertSame('CC BY 4.0', $picture->license);
    }

    public function testMissingCategoryDoesNotFallBackToAnUnrelatedPhotograph(): void
    {
        $this->expectException(\RuntimeException::class);
        (new FixtureMediaLibrary($this->directory))->forCategory('catalog-sports-outdoor-cycling-accessories', 1);
    }

    public function testPictureCreditsAndUrlsSurviveTheCatalogSerializationGroups(): void
    {
        $element = (new CatalogElements())->setName('Office Chair')->setPicture($this->media());
        $element->getProduct()->setId(1);
        $dto = (new CatalogResponseFactory(new CatalogPictureResponseFactory('https://shop.example/media')))->elementFromEntity($element, false);
        $serializer = new Serializer([new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()))], [new JsonEncoder()]);
        $data = json_decode($serializer->serialize($dto, 'json', ['groups' => ['catalog_element:list']]), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('https://shop.example/media/'.$this->key, $data['picture']['fullUrl']);
        self::assertSame('Photographer', $data['picture']['creator']);
        self::assertArrayNotHasKey('pictureId', $data);
    }

    public function testDryRunValidatesLocallyWithoutResolvingTheImporter(): void
    {
        $library = $this->libraryForAllCategories();
        $fixture = new CatalogMediaFixtures($library, static function (): never {
            self::fail('Dry-run must not initialize S3 or import database metadata.');
        }, new ArrayInput(['--dry-run' => true]));
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::never())->method('flush');
        $fixture->load($manager);
        file_put_contents($this->directory.'/source.jpg', 'tampered');
        $this->expectException(\RuntimeException::class);
        $fixture->load($manager);
    }

    public function testNativeLoaderOrdersMediaBeforeProductsAndSnapshots(): void
    {
        $library = $this->libraryForAllCategories();
        $fixture = new CatalogMediaFixtures($library, static function (): never {
            self::fail('Fixture discovery must not initialize the importer.');
        }, new ArrayInput([]));
        $loader = new SymfonyFixturesLoader();
        $loader->addFixtures([
            ['fixture' => new ProductSnapshotFixtures(), 'groups' => []],
            ['fixture' => new AppFixtures($library), 'groups' => []],
            ['fixture' => $fixture, 'groups' => []],
        ]);
        self::assertSame([CatalogMediaFixtures::class, AppFixtures::class, ProductSnapshotFixtures::class],
            array_map(get_class(...), array_values($loader->getFixtures())));
        self::assertSame([ProductSnapshotFixtures::class],
            array_map(get_class(...), array_values($loader->getFixtures(['product_snapshots']))));
    }

    public function testConditionalPhotoResponseDoesNotReadStorage(): void
    {
        $library = new FixtureMediaLibrary($this->directory);
        $hash = $library->find($this->key)->variants['main']->sha256;
        $request = Request::create('/api/catalog/media/'.$this->key, server: ['HTTP_IF_NONE_MATCH' => '"'.$hash.'"']);
        $storage = new CatalogMediaStorage($this->client(static function (): never {
            self::fail('A matching ETag must not read S3.');
        }), 'photos');
        $response = (new CatalogMediaController())('office-chair', '0123456789abcdef', 'main', $request, $this->repository(), $storage);
        self::assertSame(304, $response->getStatusCode());
    }

    public function testRetiredPicturesRemainResolvableButAreNotSelectedForNewProducts(): void
    {
        $path = $this->directory.'/manifest.json';
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $old = $manifest['assets'][0];
        $current = $old;
        $current['assetId'] = 'new-chair';
        foreach ($current['variants'] as &$file) {
            $file['key'] = str_replace('/office-chair/', '/new-chair/', $file['key']);
        }
        unset($file);
        $manifest['assets'] = [$current];
        $manifest['retiredAssets'] = [$old];
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
        $library = new FixtureMediaLibrary($this->directory);
        self::assertCount(2, $library->assets());
        self::assertSame('new-chair', $library->forCategory('catalog-home-garden-furniture-office', 100)->id);
        self::assertSame('office-chair', $library->find($this->key)->id);
    }

    public function testTamperedSourceFailsBeforeAnyStorageRequest(): void
    {
        file_put_contents($this->directory.'/source.jpg', 'changed');
        $client = $this->client(static function (): never {
            self::fail('Storage must not be touched before local verification succeeds.');
        });
        $this->expectException(\RuntimeException::class);
        $this->importer(new FixtureMediaLibrary($this->directory), new CatalogMediaStorage($client, 'photos'))->import();
    }

    public function testImportUploadsOnlyVariantsAndReusesObjects(): void
    {
        $objects = [];
        $puts = 0;
        $client = $this->client(static function (CommandInterface $command) use (&$objects, &$puts) {
            self::assertSame('photos', $command['Bucket']);
            $key = $command['Bucket'].'/'.($command['Key'] ?? '');
            if ($command->getName() === 'HeadBucket') {
                return Create::promiseFor(new Result());
            }
            if ($command->getName() === 'HeadObject') {
                if (!isset($objects[$key])) {
                    return Create::rejectionFor(new S3Exception('Not found', $command, ['response' => new Response(404)]));
                }
                return Create::promiseFor(new Result(['Metadata' => $objects[$key]]));
            }
            if ($command->getName() === 'PutObject') {
                self::assertSame('image/webp', $command['ContentType']);
                self::assertStringEndsWith('.webp', $command['Key']);
                ++$puts;
                $objects[$key] = $command['Metadata'];
                return Create::promiseFor(new Result());
            }
            self::fail('Unexpected storage command '.$command->getName());
        });
        $storage = new CatalogMediaStorage($client, 'photos');
        $library = new FixtureMediaLibrary($this->directory);
        $importer = $this->importer($library, $storage);
        self::assertSame(3, $importer->import());
        self::assertSame(0, $importer->import());
        self::assertSame(3, $puts);
        self::assertCount(3, $objects);
        self::assertFileExists($this->directory.'/source.jpg');
        self::assertArrayHasKey('photos/'.$this->key, $objects);
    }

    public function testMediaFixtureReusesStoredFilesWithoutCreatingUnownedMetadata(): void
    {
        $library = $this->libraryForAllCategories();
        $objects = [];
        $puts = 0;
        $storage = new CatalogMediaStorage($this->client(static function (CommandInterface $command) use (&$objects, &$puts) {
            self::assertSame('photos', $command['Bucket']);
            $key = $command['Bucket'].'/'.($command['Key'] ?? '');
            if ($command->getName() === 'HeadObject') {
                return isset($objects[$key])
                    ? Create::promiseFor(new Result(['Metadata' => $objects[$key]]))
                    : Create::rejectionFor(new S3Exception('Not found', $command, ['response' => new Response(404)]));
            }
            if ($command->getName() === 'PutObject') {
                self::assertSame('image/webp', $command['ContentType']);
                self::assertStringEndsWith('.webp', $command['Key']);
                ++$puts;
                $objects[$key] = $command['Metadata'];
            }
            return Create::promiseFor(new Result());
        }), 'photos');
        $importer = $this->importer($library, $storage);
        $fixture = new CatalogMediaFixtures($library, static fn () => $importer, new ArrayInput([]));
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::never())->method('flush');
        $fixture->load($manager);
        $fixture->load($manager);
        self::assertSame(3, $puts);
    }

    private function libraryForAllCategories(): FixtureMediaLibrary
    {
        $path = $this->directory.'/manifest.json';
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $manifest['assets'][0]['categorySlugs'] = AppFixtures::mediaCategorySlugs();
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
        return new FixtureMediaLibrary($this->directory);
    }

    public function testFailedUploadStopsImport(): void
    {
        $storage = new CatalogMediaStorage($this->client(static function (CommandInterface $command) {
            if ($command->getName() === 'HeadObject') {
                return Create::rejectionFor(new S3Exception('Not found', $command, ['response' => new Response(404)]));
            }
            if ($command->getName() === 'PutObject') {
                throw new \RuntimeException('Upload failed');
            }
            return Create::promiseFor(new Result());
        }), 'photos');
        $this->expectExceptionMessage('Upload failed');
        $this->importer(new FixtureMediaLibrary($this->directory), $storage)->import();
    }

    public function testHeadChecksExistenceWithoutDownloadingTheObject(): void
    {
        $client = $this->client(function (CommandInterface $command) {
            self::assertSame('HeadObject', $command->getName());
            self::assertSame($this->key, $command['Key']);
            return Create::promiseFor(new Result());
        });
        $response = (new CatalogMediaController())('office-chair', '0123456789abcdef', 'main',
            Request::create('/api/catalog/media/'.$this->key, 'HEAD'), $this->repository(),
            new CatalogMediaStorage($client, 'photos'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
    }

    private function repository(): CatalogMediaRepository
    {
        $repository = $this->createMock(CatalogMediaRepository::class);
        $repository->method('findOneByPictureId')->with($this->key)->willReturn($this->media());
        return $repository;
    }

    private function media(): CatalogMedia
    {
        $variant = fn (string $name): CatalogMediaVariant => new CatalogMediaVariant(dirname($this->key).'/'.$name.'.webp', hash_file('sha256', $this->directory.'/'.$name.'.webp'));
        return new CatalogMedia($this->key, $variant('thumb'), $variant('card'), $variant('main'),
            'Office chair', 'Photographer', 'https://example.com/photo', 'CC BY 4.0',
            'https://creativecommons.org/licenses/by/4.0/', 'Photo by Photographer, CC BY 4.0; resized.');
    }

    private function importer(FixtureMediaLibrary $library, CatalogMediaStorage $storage): FixtureMediaImporter
    {
        return new FixtureMediaImporter($library, $storage);
    }

    public function testDocumentPictureResolutionIsIndependentOfManifest(): void
    {
        $document = ProductSearchPicture::fromEntity($this->media())->toArray();
        unlink($this->directory.'/manifest.json');
        $picture = (new CatalogPictureResponseFactory('https://shop.example/media'))->fromDocument($document);
        self::assertSame('Photographer', $picture->creator);
        self::assertSame('https://shop.example/media/'.$this->key, $picture->fullUrl);
        $moved = (new CatalogPictureResponseFactory('https://cdn.example/media/'))->fromDocument($document);
        self::assertSame('https://cdn.example/media/'.$this->key, $moved->fullUrl);
        self::assertSame($picture->attribution, $moved->attribution);
    }

    private function client(callable $handler): S3Client
    {
        return new S3Client(['version' => 'latest', 'region' => 'us-east-1', 'endpoint' => 'http://storage.invalid', 'credentials' => ['key' => 'test', 'secret' => 'test'], 'handler' => $handler]);
    }
}
