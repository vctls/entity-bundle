<?php

namespace Vctls\EntityBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * User: vtoulouse
 * Date: 22/02/2018
 * Time: 13:11
 */

class DefaultType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @throws \ReflectionException
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $object = $builder->getData();
        $class = get_class($object);

        $methodNames = get_class_methods($class);

        foreach ($methodNames as $key => $methodName) {
            if (strpos($methodName, 'set') === 0) {
                $field = [];

                $field[0] = substr($methodName, 3);

                $reflectionMethod = new \ReflectionMethod($class, $methodName);
                $reflectionParams = $reflectionMethod->getParameters();
                $paramType = $reflectionParams[0]->getType();

                if (isset($paramType)) {
                    $paramTypeName = $paramType->getName();

                    switch ($paramTypeName) {
                        case 'DateTime' :
                            $field[1] = 'Symfony\Component\Form\Extension\Core\Type\DateTimeType';
                            $field[2] = ['data' => new \DateTime(), 'date_widget' => 'single_text', 'time_widget' => 'single_text'];
                            break;
                    }
                }

                $builder->add(...$field);
            }
        }
    }
}