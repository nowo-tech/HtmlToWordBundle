<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoHtmlSamples;
use JsonException;
use Nowo\HtmlToWordBundle\Converter\HtmlToWordConverterInterface;
use Nowo\HtmlToWordBundle\Exception\InvalidProfileException;
use Nowo\HtmlToWordBundle\Export\ExporterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function array_keys;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Demo: always the same pipeline (phpword engine); controller selects converter methods.
 */
final class HtmlToWordDemoController extends AbstractController
{
    private const API_CONVERT = 'convert';

    private const API_PROFILE = 'convertWithProfile';

    private const API_OPTIONS = 'convertWithOptions';

    private const MODE_MERGE = 'merge';

    private const MODE_INLINE = 'inline';

    /**
     * @param array<string, mixed> $htmlToWordProfiles
     */
    public function __construct(
        #[Autowire('%nowo_html_to_word.profiles%')]
        private readonly array $htmlToWordProfiles,
        #[Autowire('%nowo_html_to_word.default_profile%')]
        private readonly string $htmlToWordDefaultProfile,
    ) {
    }

    #[Route('/', name: 'demo_home', methods: ['GET', 'POST'])]
    public function home(
        Request $request,
        HtmlToWordConverterInterface $converter,
        ExporterInterface $exporter,
    ): Response {
        $profileKeys = array_keys($this->htmlToWordProfiles);

        if ($request->isMethod('POST')) {
            $profile = $request->request->getString('profile');
            if (!in_array($profile, $profileKeys, true)) {
                $profile = in_array($this->htmlToWordDefaultProfile, $profileKeys, true)
                    ? $this->htmlToWordDefaultProfile
                    : ($profileKeys[0] ?? 'default');
            }

            $samplePath = $this->getParameter('kernel.project_dir').'/public/demo/sample.png';
            $preset     = $request->request->getString('preset');
            $html       = DemoHtmlSamples::html($preset);
            if ($html === null) {
                $html = $request->request->getString('html');
            }
            if ($html === '') {
                $html = '<p><em>Empty</em> — pick a preset or paste HTML.</p>';
            }
            $html = str_replace(DemoHtmlSamples::DEMO_SAMPLE_IMAGE_PLACEHOLDER, $samplePath, $html);

            $api = $request->request->getString('api', self::API_PROFILE);
            if (!in_array($api, [self::API_CONVERT, self::API_PROFILE, self::API_OPTIONS], true)) {
                $api = self::API_PROFILE;
            }

            try {
                $document = match ($api) {
                    self::API_CONVERT => $converter->convert($html),
                    self::API_OPTIONS => $converter->convertWithOptions($html, [
                        'export' => [
                            'filename' => sprintf(
                                'demo-convert-with-options-%s.docx',
                                preg_replace('/[^a-z0-9_-]+/i', '-', $profile),
                            ),
                        ],
                    ], $profile),
                    default => $converter->convertWithProfile($html, $profile),
                };
            } catch (InvalidProfileException) {
                $document = $converter->convert($html);
            }

            return $exporter->toStreamResponse($document);
        }

        $samplePath = $this->getParameter('kernel.project_dir').'/public/demo/sample.png';
        $presets    = DemoHtmlSamples::presetsWithResolvedSampleImage($samplePath);

        return $this->render('demo/index.html.twig', [
            'profiles'         => $profileKeys,
            'presets'          => $presets,
            'preset_meta'      => DemoHtmlSamples::PRESET_META,
            'preset_meta_json' => json_encode(DemoHtmlSamples::PRESET_META, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'presets_json'     => json_encode($presets, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
            'api_methods'      => [
                self::API_PROFILE => 'convertWithProfile($html, $profile) — uses the selected YAML profile.',
                self::API_CONVERT => 'convert($html) — always the YAML default profile (ignores profile selector).',
                self::API_OPTIONS => 'convertWithOptions($html, overlay export filename, $profile) — merges options onto the profile.',
            ],
        ]);
    }

    #[Route('/custom-config', name: 'demo_custom_config', methods: ['GET', 'POST'])]
    public function customConfig(
        Request $request,
        HtmlToWordConverterInterface $converter,
        ExporterInterface $exporter,
    ): Response {
        $profileKeys  = array_keys($this->htmlToWordProfiles);
        $sampleSource = $this->htmlToWordProfiles[$this->htmlToWordDefaultProfile]
            ?? ($this->htmlToWordProfiles[$profileKeys[0] ?? ''] ?? []);
        $sampleConfigJson = json_encode($sampleSource, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $headerFooterDemoJson = json_encode(
            DemoHtmlSamples::headerFooterDemoOverlay((string) $this->getParameter('kernel.project_dir')),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if ($request->isMethod('POST')) {
            $rawJson = $request->request->getString('config_json');
            try {
                $options = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->addFlash('danger', 'Invalid JSON: ' . $e->getMessage());

                return $this->redirectToRoute('demo_custom_config');
            }

            if (!is_array($options)) {
                $this->addFlash('danger', 'Configuration must be a JSON object (associative array at the root).');

                return $this->redirectToRoute('demo_custom_config');
            }

            $html = $request->request->getString('html');
            if ($html === '') {
                $html = '<p><em>No HTML</em> — using a minimal fallback paragraph.</p>';
            }

            $mode = $request->request->getString('mode', self::MODE_MERGE);
            if (!in_array($mode, [self::MODE_MERGE, self::MODE_INLINE], true)) {
                $mode = self::MODE_MERGE;
            }

            try {
                if ($mode === self::MODE_INLINE) {
                    $document = $converter->convertWithInlineProfile($html, $options);
                } else {
                    $profile = $request->request->getString('profile');
                    if (!in_array($profile, $profileKeys, true)) {
                        $profile = in_array($this->htmlToWordDefaultProfile, $profileKeys, true)
                            ? $this->htmlToWordDefaultProfile
                            : ($profileKeys[0] ?? 'default');
                    }
                    $document = $converter->convertWithOptions($html, $options, $profile);
                }
            } catch (InvalidProfileException) {
                $this->addFlash('danger', 'Unknown YAML profile for merge mode.');

                return $this->redirectToRoute('demo_custom_config');
            }

            return $exporter->toStreamResponse($document);
        }

        return $this->render('demo/custom_config.html.twig', [
            'profiles'                   => $profileKeys,
            'default_profile'            => $this->htmlToWordDefaultProfile,
            'sample_config_json'         => $sampleConfigJson,
            'header_footer_demo_json'    => $headerFooterDemoJson,
            'default_custom_config_html' => DemoHtmlSamples::CUSTOM_CONFIG_DEFAULT_HTML,
        ]);
    }
}
