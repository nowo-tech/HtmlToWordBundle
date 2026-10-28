<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function implode;
use function in_array;
use function sprintf;

/**
 * Root config key: {@see ALIAS} — named profiles + default_profile.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_html_to_word';

    public const PACKAGE = 'nowo-tech/html-to-word-bundle';

    /** @var list<string> */
    public const SUPPORTED_ENGINES = [
        'phpword',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('engine')
                    ->defaultValue('phpword')
                    ->info('Conversion backend: phpword (PHPWord). Add new engines via tagged WordEngineInterface services and extend SUPPORTED_ENGINES.')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('default_profile')->defaultValue('default')->end()
                ->arrayNode('profiles')
                    ->useAttributeAsKey('name')
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('strict_mode')->defaultFalse()->end()
                            ->arrayNode('page')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('size')->defaultValue('A4')->end()
                                    ->scalarNode('orientation')->defaultValue('portrait')->end()
                                    ->scalarNode('custom_width')->defaultNull()->end()
                                    ->scalarNode('custom_height')->defaultNull()->end()
                                    ->arrayNode('margins')
                                        ->addDefaultsIfNotSet()
                                        ->children()
                                            ->integerNode('top')->defaultValue(1440)->end()
                                            ->integerNode('right')->defaultValue(1440)->end()
                                            ->integerNode('bottom')->defaultValue(1440)->end()
                                            ->integerNode('left')->defaultValue(1440)->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('fonts')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('default')->defaultValue('Arial')->end()
                                    ->scalarNode('fallback')
                                        ->defaultValue('Times New Roman')
                                        ->setDeprecated(self::PACKAGE, '1.4', 'The "%path%.%node%" option is deprecated and has no effect: Word has no font fallback chain; set "fonts.default" only.')
                                    ->end()
                                    ->scalarNode('size_unit')
                                        ->defaultValue('pt')
                                        ->setDeprecated(self::PACKAGE, '1.4', 'The "%path%.%node%" option is deprecated and has no effect: "fonts.default_size" is always in points.')
                                    ->end()
                                    ->floatNode('default_size')->defaultValue(11.0)->end()
                                ->end()
                            ->end()
                            ->arrayNode('styles')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('heading_map')
                                        ->addDefaultsIfNotSet()
                                        ->children()
                                            ->scalarNode('h1')->defaultValue('Heading1')->end()
                                            ->scalarNode('h2')->defaultValue('Heading2')->end()
                                            ->scalarNode('h3')->defaultValue('Heading3')->end()
                                            ->scalarNode('h4')->defaultValue('Heading4')->end()
                                            ->scalarNode('h5')->defaultValue('Heading5')->end()
                                            ->scalarNode('h6')->defaultValue('Heading6')->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('paragraph_spacing')
                                        ->addDefaultsIfNotSet()
                                        ->children()
                                            ->integerNode('before')->defaultValue(0)->end()
                                            ->integerNode('after')->defaultValue(160)->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('custom_class_map')
                                        ->scalarPrototype()->end()
                                        ->defaultValue([])
                                        ->setDeprecated(self::PACKAGE, '1.4', 'The "%path%.%node%" option is deprecated and has no effect: CSS classes are not mapped to Word styles; use a custom transformer instead.')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('header')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('enabled')->defaultFalse()->end()
                                    ->scalarNode('logo')->defaultNull()->end()
                                    ->integerNode('logo_width')->defaultValue(100)->end()
                                    ->booleanNode('show_page_number')->defaultFalse()->end()
                                    ->scalarNode('text')->defaultNull()->end()
                                    ->booleanNode('different_first_page')->defaultFalse()->end()
                                ->end()
                            ->end()
                            ->arrayNode('footer')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('enabled')->defaultFalse()->end()
                                    ->booleanNode('show_page_number')->defaultTrue()->end()
                                    ->scalarNode('text')->defaultNull()->end()
                                    ->booleanNode('different_first_page')->defaultFalse()->end()
                                ->end()
                            ->end()
                            ->arrayNode('images')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->integerNode('max_width')->defaultValue(600)->end()
                                    ->booleanNode('resolve_remote')
                                        ->info('Download remote http(s) images. Requires remote_host_allowlist when true.')
                                        ->defaultFalse()
                                    ->end()
                                    ->arrayNode('remote_host_allowlist')
                                        ->info('Allowed hosts for remote images, matched against the URL host only: example.com (host + subdomains), .example.com (subdomains only), or regex on the host (# prefix). Required when resolve_remote is true.')
                                        ->scalarPrototype()->end()
                                        ->defaultValue([])
                                    ->end()
                                    ->integerNode('data_uri_max_bytes')
                                        ->info('Maximum decoded size in bytes of one data: URI image.')
                                        ->defaultValue(10485760)
                                        ->min(1)
                                    ->end()
                                    ->integerNode('max_count')
                                        ->info('Maximum number of <img> tags in one HTML document (data:, remote and local together).')
                                        ->defaultValue(200)
                                        ->min(0)
                                    ->end()
                                    ->integerNode('remote_max_bytes')
                                        ->info('Maximum size in bytes of a downloaded remote image; the download is aborted when exceeded.')
                                        ->defaultValue(10485760)
                                        ->min(1)
                                    ->end()
                                    ->booleanNode('remote_allow_private_network')
                                        ->info('Allow remote images on private/loopback/link-local/reserved addresses (intranet only). Default false: hosts must resolve only to public IPs.')
                                        ->defaultFalse()
                                    ->end()
                                    ->scalarNode('local_base_dir')
                                        ->info('Directory from which local <img src> paths may be read (absolute or relative to it). Null (default) = local paths in HTML are rejected.')
                                        ->defaultNull()
                                    ->end()
                                    ->floatNode('remote_timeout')
                                        ->info('HTTP(S) download timeout in seconds for remote images (stream_context). Keep below PHP max_execution_time / FrankenPHP write timeout.')
                                        ->defaultValue(10.0)
                                        ->min(0.1)
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('limits')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->integerNode('max_html_bytes')
                                        ->info('Maximum size in bytes of the HTML passed to the converter (embedded data: images included).')
                                        ->defaultValue(33554432)
                                        ->min(1)
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('export')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('filename')->defaultValue('document.docx')->end()
                                    ->scalarNode('storage')
                                        ->defaultValue('memory')
                                        ->setDeprecated(self::PACKAGE, '1.4', 'The "%path%.%node%" option is deprecated and has no effect: choose the target by calling the exporter (toStreamResponse/toBinaryResponse/toFile/toFlysystem).')
                                    ->end()
                                    ->scalarNode('local_path')
                                        ->defaultNull()
                                        ->setDeprecated(self::PACKAGE, '1.4', 'The "%path%.%node%" option is deprecated and has no effect: pass the path to DocxExporter::toFile().')
                                    ->end()
                                    ->scalarNode('flysystem_adapter')
                                        ->info('Service id of a League\Flysystem\FilesystemOperator used by DocxExporter::toFlysystem() for documents of this profile (requires league/flysystem). Null = the exporter\'s injected $flysystem.')
                                        ->defaultNull()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('tables')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('repeat_header_row')->defaultTrue()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
            ->always(static function (array $v): array {
                if (!in_array($v['engine'], self::SUPPORTED_ENGINES, true)) {
                    throw new InvalidConfigurationException(sprintf('nowo_html_to_word.engine must be one of: %s (got "%s").', implode(', ', self::SUPPORTED_ENGINES), $v['engine']));
                }

                if (!isset($v['profiles'][$v['default_profile']])) {
                    throw new InvalidConfigurationException(sprintf('nowo_html_to_word.default_profile ("%s") must exist in nowo_html_to_word.profiles (keys: %s).', $v['default_profile'], implode(', ', array_keys($v['profiles']))));
                }

                return $v;
            })
            ->end();

        return $treeBuilder;
    }
}
