<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CsvUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('csv', FileType::class, [
            'label' => 'Choisissez un fichier CSV',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new File([
                    'mimeTypes' => [
                        'text/plain',
                        'text/csv',
                        'application/vnd.ms-excel'
                    ],
                    'mimeTypesMessage' => 'Seuls les fichiers CSV sont autorisés.',
                ]),
                new Callback(function ($file, ExecutionContextInterface $context) {
                    if ($file && strtolower($file->getClientOriginalExtension()) !== 'csv') {
                        $context->buildViolation('Le fichier doit avoir l’extension .csv.')
                            ->addViolation();
                    }
                }),
            ],
        ]);
    }










    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false, // juste pour test mais on va le laisser peut-être 
            //il tester sur le serveur de production
        ]);
    }
}
