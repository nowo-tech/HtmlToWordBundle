<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\DependencyInjection;

use Nowo\HtmlToWordBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testInvalidEngineThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_html_to_word.engine must be one of');

        (new Processor())->processConfiguration(new Configuration(), [[
            'engine'          => 'unknown-engine',
            'default_profile' => 'default',
            'profiles'        => [
                'default' => ['strict_mode' => false],
            ],
        ]]);
    }

    public function testDefaultProfileMustExist(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('default_profile');

        (new Processor())->processConfiguration(new Configuration(), [[
            'engine'          => 'phpword',
            'default_profile' => 'missing',
            'profiles'        => [
                'default' => ['strict_mode' => false],
            ],
        ]]);
    }

    public function testValidMinimalConfigProcesses(): void
    {
        $out = (new Processor())->processConfiguration(new Configuration(), [[
            'engine'          => 'phpword',
            'default_profile' => 'default',
            'profiles'        => [
                'default' => ['strict_mode' => false],
            ],
        ]]);

        self::assertSame('phpword', $out['engine']);
        self::assertArrayHasKey('default', $out['profiles']);
    }
}
