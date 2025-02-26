<?php

declare(strict_types=1);

namespace Panakour\ShopwareDALToolkit;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class MediaServiceHelper
{
    /**
     * @template TCollection of EntityCollection
     *
     * @param  EntityRepository<TCollection>  $mediaRepository
     * @param  EntityRepository<TCollection>  $mediaFolderRepository
     * @param  EntityRepository<TCollection>  $mediaThumbnailSizeRepository
     */
    public function __construct(
        private EntityRepository $mediaRepository,
        private EntityRepository $mediaFolderRepository,
        private EntityRepository $mediaThumbnailSizeRepository,
        private MediaService $mediaService,
        private ?string $mediaFolder = null,
        private ?int $imagesLength = 4,
    ) {}

    /**
     * @param  array<string>  $images
     * @return array<array<string, string>>
     *
     * @throws MediaHelperException
     *
     * Assigns media based on an array of URLs/base64 strings
     */
    public function assignMedia(Context $context, array $images): array
    {
        $mediaIds = [];
        $limitedImages = array_slice($images, 0, $this->imagesLength);
        foreach ($limitedImages as $image) {
            $mediaId = $this->isBase64($image)
                ? $this->saveBase64ImageToMedia($context, $image)
                : $this->saveImageToMedia($context, $image);

            $mediaIds[] = ['mediaId' => $mediaId];
        }

        return $mediaIds;
    }

    private function saveImageToMedia(Context $context, string $url): string
    {
        try {
            $fileContent = file_get_contents($url);
            if ($fileContent === '' || $fileContent === '0' || $fileContent === false) {
                throw MediaHelperException::uploadFailed($url, 'Failed to download image');
            }

            return $this->saveFileContentToMedia($context, $fileContent);
        } catch (\Exception $e) {
            throw MediaHelperException::uploadFailed($url, $e->getMessage());
        }
    }

    private function saveBase64ImageToMedia(Context $context, string $base64String): string
    {
        $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $base64String);
        $fileContent = base64_decode((string) $base64Image);

        if ($fileContent === '' || $fileContent === '0') {
            throw MediaHelperException::base64DecodeFailed('Empty content after decode');
        }

        return $this->saveFileContentToMedia($context, $fileContent);
    }

    private function saveFileContentToMedia(Context $context, string $fileContent): string
    {
        $tempFile = null;
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'sw_media');
            if ($tempFile === false) {
                throw MediaHelperException::contentSaveFailed('Cannot create temp file');
            }

            file_put_contents($tempFile, $fileContent);

            $mimeType = mime_content_type($tempFile);
            if ($mimeType === '' || $mimeType === '0' || $mimeType === false) {
                $mimeType = 'image/jpeg';
            }
            $extension = $this->getExtensionFromMimeType($mimeType);

            $fileName = Uuid::randomHex();
            $fileSize = filesize($tempFile);
            if ($fileSize === 0 || $fileSize === false) {
                $fileSize = 0;
            }

            $mediaFile = new MediaFile(
                $tempFile,
                $mimeType,
                $extension,
                $fileSize
            );

            $mediaId = Uuid::randomHex();

            $mediaData = [
                'id' => $mediaId,
                'name' => $fileName,
                'fileExtension' => $extension,
                'mimeType' => $mimeType,
            ];

            if ($this->mediaFolder !== null && $this->mediaFolder !== '' && $this->mediaFolder !== '0') {
                $mediaData['mediaFolderId'] = $this->getMediaFolderId($context);
            }

            $this->mediaRepository->create([$mediaData], $context);

            $this->mediaService->saveMediaFile(
                $mediaFile,
                $fileName.'.'.$extension,
                $context,
                'product_media_folder',
                $mediaId
            );

            return $mediaId;
        } catch (\Exception $e) {
            throw MediaHelperException::contentSaveFailed($e->getMessage());
        } finally {
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function isBase64(string $string): bool
    {
        if (preg_match('/^data:image\/\w+;base64,/', $string)) {
            $string = preg_replace('/^data:image\/\w+;base64,/', '', $string);
        }

        $string = trim((string) $string);

        $decoded = base64_decode($string, true);
        if ($decoded === false) {
            return false;
        }

        return $decoded !== '';
    }

    private function getMediaFolderId(Context $context): string
    {
        $criteria = new Criteria;
        $criteria->addFilter(new EqualsFilter('name', $this->mediaFolder));
        $mediaFolder = $this->mediaFolderRepository->search($criteria, $context)->first();

        if ($mediaFolder instanceof MediaFolderEntity) {
            return $mediaFolder->getId();
        }
        $mediaFolderId = Uuid::randomHex();
        $this->mediaFolderRepository->create([
            [
                'id' => $mediaFolderId,
                'name' => $this->mediaFolder,
                'configuration' => [
                    'createThumbnails' => true,
                    'thumbnailQuality' => 80,
                    'mediaThumbnailSizes' => $this->getMediaThumbnailSizes($context),
                    'private' => false,
                ],
                'useParentConfiguration' => false,
                'level' => 1,
            ],
        ], $context);

        return $mediaFolderId;
    }

    /**
     * @return array<array<string, string>>
     */
    private function getMediaThumbnailSizes(Context $context): array
    {
        $sizes = [
            ['width' => 500, 'height' => 500],
        ];

        $existingSizes = [];
        foreach ($sizes as $size) {
            $criteria = new Criteria;
            $criteria->addFilter(new EqualsFilter('width', $size['width']));
            $criteria->addFilter(new EqualsFilter('height', $size['height']));

            $existingSize = $this->mediaThumbnailSizeRepository->search($criteria, $context)->first();

            if ($existingSize instanceof MediaThumbnailSizeEntity) {
                $existingSizes[] = [
                    'id' => $existingSize->getId(),
                ];
            }
        }

        return $existingSizes;
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tif',
            'application/postscript' => 'eps',
            'image/x-eps' => 'eps',
            'video/webm' => 'webm',
            'video/x-matroska' => 'mkv',
            'video/x-flv' => 'flv',
            'video/ogg' => 'ogv',
            'audio/ogg' => 'oga',
            'video/quicktime' => 'mov',
            'video/mp4' => 'mp4',
            'video/x-msvideo' => 'avi',
            'video/x-ms-wmv' => 'wmv',
            'application/pdf' => 'pdf',
            'audio/aac' => 'aac',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/flac' => 'flac',
            'audio/x-flac' => 'flac',
            'audio/x-ms-wma' => 'wma',
            'text/plain' => 'txt',
            'application/msword' => 'doc',
            'model/gltf-binary' => 'glb',
        ];

        if (isset($mimeMap[$mimeType])) {
            return $mimeMap[$mimeType];
        }

        throw MediaHelperException::contentSaveFailed('Unsupported file type: '.$mimeType);
    }
}
