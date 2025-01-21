<?php

declare(strict_types=1);

namespace Panakour\ShopwareDALToolkit;

use Exception;

class MediaHelperException extends Exception
{
    public static function uploadFailed(string $url, string $message): self
    {
        return new self(
            sprintf('Failed to upload media from URL "%s": %s', $url, $message)
        );
    }

    public static function base64DecodeFailed(string $message): self
    {
        return new self(
            sprintf('Failed to decode base64 image: %s', $message)
        );
    }

    public static function contentSaveFailed(string $message): self
    {
        return new self(
            sprintf('Failed to save media content: %s', $message)
        );
    }
}
