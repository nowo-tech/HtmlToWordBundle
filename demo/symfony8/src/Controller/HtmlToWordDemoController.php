<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoHtmlSamples;
use App\Form\DemoCustomConfigType;
use App\Form\DemoExportType;
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
use function is_string;
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
        $apiMethods  = [
            self::API_PROFILE => 'convertWithProfile($html, $profile) — uses the selected YAML profile.',
            self::API_CONVERT => 'convert($html) — always the YAML default profile (ignores profile selector).',
            self::API_OPTIONS => 'convertWithOptions($html, overlay export filename, $profile) — merges options onto the profile.',
        ];
        $form = $this->createForm(DemoExportType::class, [
            'api'     => self::API_PROFILE,
            'profile' => in_array('default', $profileKeys, true) ? 'default' : null,
            'html'    => '<p>Paste HTML or click a preset.</p>',
        ], [
            'action'      => $this->generateUrl('demo_home'),
            'profiles'    => $profileKeys,
            'api_methods' => $apiMethods,
            'attr'        => ['id' => 'export-form', 'class' => 'mb-4'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Invalid choices come back as null: keep the previous fallbacks (default profile / convertWithProfile).
            $profile = self::field($form->get('profile')->getData());
            if (!in_array($profile, $profileKeys, true)) {
                $profile = in_array($this->htmlToWordDefaultProfile, $profileKeys, true)
                    ? $this->htmlToWordDefaultProfile
                    : ($profileKeys[0] ?? 'default');
            }

            $samplePath = $this->getParameter('kernel.project_dir') . '/public/demo/sample.png';
            $preset     = self::field($form->get('preset')->getData());
            $html       = DemoHtmlSamples::html($preset);
            if ($html === null) {
                $html = self::field($form->get('html')->getData());
            }
            if ($html === '') {
                $html = '<p><em>Empty</em> — pick a preset or paste HTML.</p>';
            }
            $html = str_replace(DemoHtmlSamples::DEMO_SAMPLE_IMAGE_PLACEHOLDER, $samplePath, $html);

            $api = self::field($form->get('api')->getData());
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

        $samplePath = $this->getParameter('kernel.project_dir') . '/public/demo/sample.png';
        $presets    = DemoHtmlSamples::presetsWithResolvedSampleImage($samplePath);

        return $this->render('demo/index.html.twig', [
            'presets'          => $presets,
            'preset_meta'      => DemoHtmlSamples::PRESET_META,
            'preset_meta_json' => json_encode(DemoHtmlSamples::PRESET_META, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'presets_json'     => json_encode($presets, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
            'form'             => $form->createView(),
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
        $sampleConfigJson     = json_encode($sampleSource, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $headerFooterDemoJson = json_encode(
            DemoHtmlSamples::headerFooterDemoOverlay((string) $this->getParameter('kernel.project_dir')),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $form = $this->createForm(DemoCustomConfigType::class, [
            'mode'        => self::MODE_MERGE,
            'profile'     => $this->htmlToWordDefaultProfile,
            'html'        => DemoHtmlSamples::CUSTOM_CONFIG_DEFAULT_HTML,
            'config_json' => $headerFooterDemoJson,
        ], [
            'action'   => $this->generateUrl('demo_custom_config'),
            'profiles' => $profileKeys,
            'attr'     => ['class' => 'mb-4'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $rawJson = self::field($form->get('config_json')->getData());
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

            $html = self::field($form->get('html')->getData());
            if ($html === '') {
                $html = '<p><em>No HTML</em> — using a minimal fallback paragraph.</p>';
            }

            $mode = self::field($form->get('mode')->getData());
            if (!in_array($mode, [self::MODE_MERGE, self::MODE_INLINE], true)) {
                $mode = self::MODE_MERGE;
            }

            try {
                if ($mode === self::MODE_INLINE) {
                    $document = $converter->convertWithInlineProfile($html, $options);
                } else {
                    $profile = self::field($form->get('profile')->getData());
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
            'default_profile'    => $this->htmlToWordDefaultProfile,
            'sample_config_json' => $sampleConfigJson,
            'form'               => $form->createView(),
        ]);
    }

    /**
     * Submitted scalar field value as a string ('' when empty or not a valid choice).
     */
    private static function field(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
