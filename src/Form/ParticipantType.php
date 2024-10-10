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

use JoliCode\SecretSanta\Model\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class ParticipantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('selectedUsers', ChoiceType::class, [
                'choices' => $options['available-users'],
                'choice_label' => function (User $choice) {
                    return $choice->getName();
                },
                'multiple' => true,
                'expanded' => true,
                'constraints' => [
                    new Count([
                        'min' => 2,
                        'minMessage' => 'You have to select at least 2 users',
                    ]),
                ],
                'error_bubbling' => true,
            ])
        ;

        $builder->get('selectedUsers')->addModelTransformer(new class($options) implements DataTransformerInterface {
            /** @param array<string, mixed> $options */
            public function __construct(
                private array $options,
            ) {
            }

            public function transform(mixed $value): mixed
            {
                $users = [];
                foreach ($value as $identifier) {
                    $users[] = $this->options['available-users'][$identifier];
                }

                return $users;
            }

            public function reverseTransform(mixed $value): mixed
            {
                $identifiers = [];
                /** @var User $user */
                foreach ($value as $user) {
                    $identifiers[] = $user->getIdentifier();
                }

                return $identifiers;
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'available-users' => [],
        ]);
    }
}
