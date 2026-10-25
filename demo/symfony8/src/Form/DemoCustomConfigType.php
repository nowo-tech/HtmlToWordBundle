<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_combine;

/**
 * Custom JSON configuration demo form (REQ-TWIG-005): flat field names mode / profile / html / config_json as the
 * controller reads them. Download-only generator (no state change), so no CSRF token, as before the migration.
 *
 * @extends AbstractType<array{mode?: string|null, profile?: string|null, html?: string|null, config_json?: string|null}>
 */
final class DemoCustomConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mode', ChoiceType::class, [
                'label'      => 'Mode',
                'expanded'   => true,
                'label_html' => true,
                'choices'    => [
                    'Merge JSON on top of YAML profiles (pick base profile)'                  => 'merge',
                    'JSON only (inline / typical DB) — <code>convertWithInlineProfile</code>' => 'inline',
                ],
                'row_attr' => ['class' => 'border rounded p-3 mb-3'],
            ])
            ->add('profile', ChoiceType::class, [
                'label'       => 'YAML profile (merge mode only)',
                'choices'     => array_combine($options['profiles'], $options['profiles']),
                'required'    => false,
                'placeholder' => false,
            ])
            ->add('html', TextareaType::class, [
                'label'     => 'HTML',
                'required'  => false,
                'help'      => 'Default content: long multi-page sample (tables, lists, quotes, <code>pre</code>, typography, <code>page-break</code>).',
                'help_html' => true,
                'attr'      => ['class' => 'font-monospace small', 'rows' => 28, 'spellcheck' => 'false'],
            ])
            ->add('config_json', TextareaType::class, [
                'label'     => 'Configuración JSON (cabecera / pie / export)',
                'help'      => 'Plantilla inicial: overlay con <code>header</code>, <code>footer</code> y <code>export.filename</code>. Modifica textos, quita el logo o cambia el perfil base en modo merge.',
                'help_html' => true,
                'attr'      => ['class' => 'font-monospace small', 'rows' => 22, 'spellcheck' => 'false'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'POST',
            'csrf_protection' => false,
            'profiles'        => [],
        ]);
        $resolver->setAllowedTypes('profiles', 'string[]');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
