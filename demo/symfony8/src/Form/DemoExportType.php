<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_combine;
use function htmlspecialchars;
use function sprintf;

/**
 * Main demo export form (REQ-TWIG-005): flat field names api / profile / preset / html as the controller reads them.
 * Download-only generator (no state change), so no CSRF token, as before the Form Type migration.
 *
 * @extends AbstractType<array{api?: string|null, profile?: string|null, preset?: string|null, html?: string|null}>
 */
final class DemoExportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $apiChoices = [];
        foreach ($options['api_methods'] as $key => $description) {
            $apiChoices[sprintf('<code>%s</code> — %s', htmlspecialchars($key), htmlspecialchars($description))] = $key;
        }

        $builder
            ->add('api', ChoiceType::class, [
                'label'      => 'Converter method',
                'choices'    => $apiChoices,
                'expanded'   => true,
                'label_html' => true,
                'row_attr'   => ['class' => 'border rounded p-3 mb-3'],
            ])
            ->add('profile', ChoiceType::class, [
                'label'      => 'Profile <code>nowo_html_to_word</code>',
                'label_html' => true,
                'choices'    => array_combine($options['profiles'], $options['profiles']),
            ])
            ->add('preset', HiddenType::class, [
                'required' => false,
            ])
            ->add('html', TextareaType::class, [
                'label'    => 'HTML',
                'required' => false,
                'attr'     => ['class' => 'font-monospace small', 'rows' => 18, 'spellcheck' => 'false'],
                'help'     => 'When you load a preset, the suggested profile can be applied from the table below (button tooltip = relevant stack layer).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'POST',
            'csrf_protection' => false,
            'profiles'        => [],
            'api_methods'     => [],
        ]);
        $resolver->setAllowedTypes('profiles', 'string[]');
        $resolver->setAllowedTypes('api_methods', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
