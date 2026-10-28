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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @extends AbstractType<Config>
 */
class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 800,
                        maxMessage: 'Your message is too long, it should not exceed {{ limit }} characters.',
                    ),
                ],
                'error_bubbling' => true,
            ])
            ->add('notes', CollectionType::class, [
                'entry_type' => TextareaType::class,
                'entry_options' => [
                    'constraints' => [
                        new Length(
                            max: 400,
                            maxMessage: 'Each note should contain less than {{ limit }} characters',
                        ),
                    ],
                ],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'selected-users' => [],
        ]);
    }
}
