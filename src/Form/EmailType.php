<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<EmailData>
 */
final class EmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $senders */
        $senders = $options['senders'];

        $builder
            ->add('from', ChoiceType::class, [
                'label' => 'fields.from',
                'choices' => array_combine($senders, $senders),
                'choice_translation_domain' => false,
            ])
            ->add('to', TextType::class, [
                'label' => 'fields.to',
                'help' => 'fields.to_hint',
            ])
            ->add('subject', TextType::class, [
                'label' => 'fields.subject',
            ])
            ->add('body', TextareaType::class, [
                'label' => 'fields.body',
                'help' => 'fields.body_hint',
                'attr' => ['rows' => 10],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => EmailData::class,
                'translation_domain' => 'resend_inbox',
                'csrf_token_id' => 'resend_inbox_email',
            ])
            ->setRequired('senders')
            ->setAllowedTypes('senders', 'string[]');
    }

    public function getBlockPrefix(): string
    {
        return 'resend_inbox_email';
    }
}
