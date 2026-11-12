<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_combine;
use function htmlspecialchars;
use function sprintf;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`) with CSRF. Flat field names api / profile / preset / html as the
 * controller reads them (block prefix ''), so the token field is `_token`.
 *
 * @extends AbstractType<array{api?: string|null, profile?: string|null, preset?: string|null, html?: string|null}>
 */
#[FormKitConfig('demo')]
final class DemoExportType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $apiChoices = [];
        foreach ($options['api_methods'] as $key => $description) {
            $apiChoices[sprintf('<code>%s</code> — %s', htmlspecialchars($key), htmlspecialchars($description))] = $key;
        }

        $this->addChoice($builder, 'api', [
            'label'              => 'Converter method',
            'choices'            => $apiChoices,
            'expanded'           => true,
            'label_html'         => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addChoice($builder, 'profile', [
            'label'              => 'Profile <code>nowo_html_to_word</code>',
            'label_html'         => true,
            'choices'            => array_combine($options['profiles'], $options['profiles']),
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addWithDefaults($builder, 'preset', HiddenType::class, [
            'required' => false,
        ]);
        $this->addTextarea($builder, 'html', [
            'label'              => 'HTML',
            'required'           => false,
            'translation_domain' => false,
            'placeholder'        => false,
            'attr'               => ['rows' => 18, 'spellcheck' => 'false'],
            'help'               => 'When you load a preset, the suggested profile can be applied from the table below (button tooltip = relevant stack layer).',
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
            'method'      => 'POST',
            'profiles'    => [],
            'api_methods' => [],
        ]);
        $resolver->setAllowedTypes('profiles', 'string[]');
        $resolver->setAllowedTypes('api_methods', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
