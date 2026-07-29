<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Builder;

use Nowo\HtmlToWordBundle\Builder\ImageSignatureValidator;
use PHPUnit\Framework\TestCase;

final class ImageSignatureValidatorTest extends TestCase
{
    public function testUnreadablePathReturnsFalse(): void
    {
        self::assertFalse(ImageSignatureValidator::isRasterImage('/no/such/image-' . uniqid('', true) . '.png'));
    }

    public function testShortFileReturnsFalse(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'htw_sig_');
        self::assertNotFalse($tmp);
        try {
            file_put_contents($tmp, 'AB');
            self::assertFalse(ImageSignatureValidator::isRasterImage($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    public function testJpegSignature(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'htw_sig_jpg_');
        self::assertNotFalse($tmp);
        try {
            file_put_contents($tmp, "\xff\xd8\xff\xe0" . str_repeat("\0", 12));
            self::assertTrue(ImageSignatureValidator::isRasterImage($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    public function testGif87AndGif89Signatures(): void
    {
        foreach (['GIF87a', 'GIF89a'] as $sig) {
            $tmp = tempnam(sys_get_temp_dir(), 'htw_sig_gif_');
            self::assertNotFalse($tmp);
            try {
                file_put_contents($tmp, $sig . str_repeat("\0", 10));
                self::assertTrue(ImageSignatureValidator::isRasterImage($tmp));
            } finally {
                @unlink($tmp);
            }
        }
    }
}
