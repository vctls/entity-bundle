<?php

namespace Vctls\EntityBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
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
                            $field[1] = DateTimeType::class;
                            $field[2] = ['widget' => 'single_text'];
                            break;

                        case 'bool' :
                            $field[1] = CheckboxType::class;
                            $field[2] = ['required' => false];
                            break;
                    }
                }

                $builder->add(...$field);
            }
        }
    }
}