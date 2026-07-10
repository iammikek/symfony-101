<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ItemFilterFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('GET')
            ->add('name_contains', TextType::class, [
                'required' => false,
                'label' => 'Name contains',
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'All categories',
                'label' => 'Category',
            ])
            ->add('min_price', MoneyType::class, [
                'required' => false,
                'currency' => 'USD',
                'label' => 'Min price',
                'constraints' => [new Assert\Positive()],
            ])
            ->add('max_price', MoneyType::class, [
                'required' => false,
                'currency' => 'USD',
                'label' => 'Max price',
                'constraints' => [new Assert\Positive()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
