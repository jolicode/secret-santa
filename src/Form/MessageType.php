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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('message', TextareaType::class, [
                'data' => $options['message'],
                'required' => false,
                'attr' => ['style' => 'resize: none'],
                'constraints' => new Length([
                    'max' => 800,
                    'maxMessage' => 'Your message is too long, it should not exceed {{ limit }} characters.'
                ])
            ]);
        foreach ($options['selected-users'] as $userId) {
            $builder->add('notes-' . $userId, TextType::class, [
                'required' => false,
                'constraints' => new Length([
                    'max' => 400,
                    'maxMessage' => 'Each note should contain less than {{ limit }} characters'
                ])
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'message' => '',
            'selected-users' => []
        ]);
    }
}
