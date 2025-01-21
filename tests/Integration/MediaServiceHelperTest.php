<?php

declare(strict_types=1);

namespace Panakour\ShopwareDALToolkit\Tests\Integration;

use Panakour\ShopwareDALToolkit\MediaHelperException;
use Panakour\ShopwareDALToolkit\MediaServiceHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaServiceHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MediaServiceHelper $mediaServiceHelper;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaServiceHelper = $this->getContainer()->get(MediaServiceHelper::class);
        $this->context = Context::createCLIContext();
    }

    public function test_assign_media_from_real_url(): void
    {
        $result = $this->mediaServiceHelper->assignMedia($this->context, [
            'https://placehold.co/100x120',
        ]);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('mediaId', $result[0]);
        $this->assertNotNull($this->getMediaEntity($result[0]['mediaId']));
    }

    public function test_assign_media_from_base64(): void
    {
        $image = 'data:image/png;base64,'.base64_encode($this->createTestImage());
        $result = $this->mediaServiceHelper->assignMedia($this->context, [$image]);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('mediaId', $result[0]);
        $media = $this->getMediaEntity($result[0]['mediaId']);
        $this->assertNotNull($media);
        $this->assertEquals('png', $media->getFileExtension());
        $this->assertEquals('image/png', $media->getMimeType());
    }

    public function test_assign_media_with_multiple_real_images(): void
    {
        $images = [
            'data:image/png;base64,'.base64_encode($this->createTestImage()),
            'https://placehold.co/120x120',
            'data:image/png;base64,'.base64_encode($this->createTestImage()),
        ];
        $result = $this->mediaServiceHelper->assignMedia($this->context, $images);
        $this->assertCount(3, $result);
        foreach ($result as $ref) {
            $this->assertNotNull($this->getMediaEntity($ref['mediaId']));
        }
    }

    public function test_assign_media_with_invalid_url(): void
    {
        $this->expectException(MediaHelperException::class);
        $this->mediaServiceHelper->assignMedia($this->context, [
            'https://invalid-url-that-does-not-exist.com/image.jpg',
        ]);
    }

    public function test_assign_media_with_invalid_base64(): void
    {
        $this->expectException(MediaHelperException::class);
        $this->mediaServiceHelper->assignMedia($this->context, [
            'data:image/png;base64,invalid_base64',
        ]);
    }

    public function test_assign_media_with_custom_folder(): void
    {
        $helper = new MediaServiceHelper(
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('media_folder.repository'),
            $this->getContainer()->get('media_thumbnail_size.repository'),
            $this->getContainer()->get(MediaService::class),
            'test_folder'
        );
        $image = 'data:image/png;base64,'.base64_encode($this->createTestImage());
        $result = $helper->assignMedia($this->context, [$image]);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('mediaId', $result[0]);
        $this->assertNotNull($this->getMediaFolderEntity('test_folder'));
    }

    public function test_assign_media_with_different_file_types(): void
    {
        $types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
        ];
        foreach ($types as $mime => $ext) {
            $data = 'data:'.$mime.';base64,'.base64_encode($this->createTestImage($mime));
            $result = $this->mediaServiceHelper->assignMedia($this->context, [$data]);
            $this->assertCount(1, $result);
            $this->assertArrayHasKey('mediaId', $result[0]);
            $media = $this->getMediaEntity($result[0]['mediaId']);
            $this->assertNotNull($media);
            $this->assertEquals($ext, $media->getFileExtension());
            $this->assertEquals($mime, $media->getMimeType());
        }
    }

    public function test_assign_media_respects_image_limit(): void
    {
        $images = [
            'data:image/png;base64,'.base64_encode($this->createTestImage()),
            'https://placehold.co/120x120',
            'data:image/png;base64,'.base64_encode($this->createTestImage()),
            'https://placehold.co/140x140',
            'data:image/png;base64,'.base64_encode($this->createTestImage()),
        ];
        $result = $this->mediaServiceHelper->assignMedia($this->context, $images);
        $this->assertCount(4, $result);
    }

    public function test_assign_media_with_empty_array(): void
    {
        $result = $this->mediaServiceHelper->assignMedia($this->context, []);
        $this->assertEmpty($result);
    }

    public function test_assign_media_with_unknown_mime_type(): void
    {
        $this->expectException(MediaHelperException::class);
        $bytes = base64_encode(random_bytes(32));
        $this->mediaServiceHelper->assignMedia($this->context, [
            'data:application/octet-stream;base64,'.$bytes,
        ]);
    }

    private function getMediaEntity(string $id): ?MediaEntity
    {
        $repo = $this->getContainer()->get('media.repository');

        return $repo->search(new Criteria([$id]), $this->context)->first();
    }

    private function getMediaFolderEntity(string $name): ?MediaFolderEntity
    {
        $repo = $this->getContainer()->get('media_folder.repository');
        $c = new Criteria;
        $c->addFilter(new EqualsFilter('name', $name));

        return $repo->search($c, $this->context)->first();
    }

    private function createTestImage(string $mime = 'image/png'): string
    {
        $im = imagecreatetruecolor(100, 100);
        imagefilledrectangle($im, 0, 0, 99, 99, 0xFFFFFF);
        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($im),
            'image/gif' => imagegif($im),
            default => imagepng($im),
        };
        $content = ob_get_clean();
        imagedestroy($im);

        return $content;
    }
}
