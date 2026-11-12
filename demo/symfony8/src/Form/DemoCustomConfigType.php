<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_combine;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`) with CSRF. Flat field names mode / profile / html / config_json as the
 * controller reads them (block prefix ''), so the token field is `_token`.
 *
 * @extends AbstractType<array{mode?: string|null, profile?: string|null, html?: string|null, config_json?: string|null}>
 */
#[FormKitConfig('demo')]
final class DemoCustomConfigType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addChoice($builder, 'mode', [
            'label'      => 'Mode',
            'expanded'   => true,
            'label_html' => true,
            'choices'    => [
                'Merge JSON on top of YAML profiles (pick base profile)'                  => 'merge',
                'JSON only (inline / typical DB) — <code>convertWithInlineProfile</code>' => 'inline',
            ],
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addChoice($builder, 'profile', [
            'label'              => 'YAML profile (merge mode only)',
            'choices'            => array_combine($options['profiles'], $options['profiles']),
            'required'           => false,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addTextarea($builder, 'html', [
            'label'              => 'HTML',
            'required'           => false,
            'help'               => 'Default content: long multi-page sample (tables, lists, quotes, <code>pre</code>, typography, <code>page-break</code>).',
            'help_html'          => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'attr'               => ['rows' => 28, 'spellcheck' => 'false'],
        ]);
        $this->addTextarea($builder, 'config_json', [
            'label'              => 'Configuración JSON (cabecera / pie / export)',
            'help'               => 'Plantilla inicial: overlay con <code>header</code>, <code>footer</code> y <code>export.filename</code>. Modifica textos, quita el logo o cambia el perfil base en modo merge.',
            'help_html'          => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'attr'               => ['rows' => 22, 'spellcheck' => 'false'],
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label'              => 'Download .docx',
            'translation_domain' => false,
            'attr'               => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'   => 'POST',
            'profiles' => [],
        ]);
        $resolver->setAllowedTypes('profiles', 'string[]');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
