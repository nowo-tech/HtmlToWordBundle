<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\Config;

use Nowo\HtmlToWordBundle\Config\ProfileResolver;
use Nowo\HtmlToWordBundle\Exception\InvalidProfileException;
use PHPUnit\Framework\TestCase;

final class ProfileResolverTest extends TestCase
{
    public function testResolveMergesDefaultNamedAndAdhoc(): void
    {
        $profiles = [
            'default' => [
                'strict_mode' => false,
                'fonts'       => [
                    'default'      => 'Arial',
                    'default_size' => 11,
                ],
                'export' => ['filename' => 'document.docx'],
            ],
            'letter' => [
                'fonts'  => ['default' => 'Times New Roman'],
                'export' => ['filename' => 'letter.docx'],
            ],
        ];

        $resolver = new ProfileResolver($profiles, 'default');
        $resolved = $resolver->resolve('letter', ['strict_mode' => true]);

        self::assertTrue($resolved->strictMode());
        self::assertSame('Times New Roman', $resolved->get('fonts.default'));
        self::assertSame('letter.docx', $resolved->get('export.filename'));
        self::assertEquals(11, $resolved->get('fonts.default_size'));
    }

    public function testResolveDefaultUsesDefaultProfile(): void
    {
        $profiles = [
            'default' => ['strict_mode' => false],
            'other'   => ['strict_mode' => true],
        ];
        $resolver = new ProfileResolver($profiles, 'default');
        self::assertFalse($resolver->resolveDefault()->strictMode());
    }

    public function testUnknownProfileThrows(): void
    {
        $this->expectException(InvalidProfileException::class);
        $resolver = new ProfileResolver(['default' => []], 'default');
        $resolver->resolve('missing');
    }

    public function testGetDefaultProfileKey(): void
    {
        $resolver = new ProfileResolver(['default' => [], 'x' => []], 'x');
        self::assertSame('x', $resolver->getDefaultProfileKey());
    }
}
