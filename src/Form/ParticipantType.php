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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;

class ParticipantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('users', ChoiceType::class, [
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
                'error_bubbling' => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'available-users' => [],
        ]);
    }
}
