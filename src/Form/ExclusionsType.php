<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Form;

use JoliCode\SecretSanta\Model\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ExclusionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Config $config */
        $config = $options['config'];

        $builder
            ->add('exclusions', CollectionType::class, [
                'entry_type' => ChoiceType::class,
                'entry_options' => [
                    'choices' => $config->getSelectedUsers(),
                    'choice_label' => function (string|int $userId) use ($config) {
                        return $config->getUser($userId)?->getName() ?? 'Unknown User';
                    },
                    'multiple' => true,
                    'expanded' => true,
                    'constraints' => [
                        new Callback(
                            function (array $exclusions, ExecutionContextInterface $context) {
                                $userId = $context->getObject()->getName();
                                if (\in_array($userId, $exclusions, true)) {
                                    $context->buildViolation('A user cannot exclude themselves.')->addViolation();
                                }
                            }
                        ),
                    ],
                    'error_bubbling' => true,
                ],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('config');
        $resolver->setAllowedTypes('config', Config::class);
    }
}
