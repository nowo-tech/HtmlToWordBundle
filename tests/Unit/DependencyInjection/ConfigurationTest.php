<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Tests\Unit\DependencyInjection;

use Nowo\HtmlToWordBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
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

    public function testDefaultsDoNotTriggerDeprecations(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => ['default' => []],
        ]]);

        self::assertSame('Times New Roman', $config['profiles']['default']['fonts']['fallback']);
        self::assertNull($config['profiles']['default']['export']['flysystem_adapter']);
    }

    #[IgnoreDeprecations]
    public function testDeprecatedNoOpKeysTriggerDeprecations(): void
    {
        foreach (['fallback', 'size_unit', 'custom_class_map', 'storage', 'local_path'] as $key) {
            $this->expectUserDeprecationMessageMatches('/^Since nowo-tech\/html-to-word-bundle 1\.4: The "nowo_html_to_word\.profiles\.default\.\w+\.' . $key . '" option is deprecated/');
        }

        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'fonts'  => ['fallback' => 'Arial', 'size_unit' => 'pt'],
                    'styles' => ['custom_class_map' => ['lead' => 'Lead']],
                    'export' => ['storage' => 'local', 'local_path' => '/tmp', 'flysystem_adapter' => 'app.fs'],
                ],
            ],
        ]]);

        self::assertSame('app.fs', $config['profiles']['default']['export']['flysystem_adapter']);
    }
}
