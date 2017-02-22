<?php

namespace Vctls\EntityBundle\Normalizer;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class EntityNormalizer extends AbstractNormalizer
{
    /**
     * Transform an Entity object into a flat, one-dimensional array of scalar values.
     *
     * @param object $object
     * @param null $format
     * @param array $context
     * @return array|mixed
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object);
        }

        $reflectionObject = new \ReflectionObject($object);
        /** @var \ReflectionMethod[] $reflectionMethods */
        $reflectionMethods = $reflectionObject->getMethods(\ReflectionMethod::IS_PUBLIC);
        $allowedAttributes = $this->getAllowedAttributes($object, $context, true);

        $attributes = array();
        foreach ($reflectionMethods as $method) {

            if ($this->isGetMethod($method)) {

                $attributeName = lcfirst(substr($method->name, 0 === strpos($method->name, 'is') ? 2 : 3));

                if (in_array($attributeName, $this->ignoredAttributes)) {
                    continue;
                }

                if (false !== $allowedAttributes && !in_array($attributeName, $allowedAttributes)) {
                    continue;
                }

                $attributeValue = $method->invoke($object);
                if (isset($this->callbacks[$attributeName])) {
                    $attributeValue = call_user_func($this->callbacks[$attributeName], $attributeValue);
                }

                if ( is_array($attributeValue) ) {
                    $data = [];
                    foreach ($attributeValue as $key => $value) {
                        // If the key is not simply an index, print the pair.
                        array_push($data, is_numeric($key) ? "$value" : "$key => $value");
                    }
                    $attributeValue = implode(", " , $data);
                } elseif ( null !== $attributeValue && !is_scalar($attributeValue) ) {

                    // TODO You can do better than this.

                    if ($attributeValue instanceof \DateTime) {
                        // If the object is a date, return a formatted string.
                        $attributeValue = $attributeValue->format("Y:m:d H:i:s");

                    } elseif (
                        // TODO Find a better way. Inject the Doctrine Metadata Factory?
                        method_exists($attributeValue, "__toString") &&
                        method_exists($attributeValue, "getId")
                    ) {
                        /*
                         * If the value is a Doctrine entity, return an array containing:
                         *   - the Id
                         *   - the entity name
                         *   - the display value
                         */

                        // TODO Find a better way.
                        $class = get_class($attributeValue);
                        $realClass = ClassUtils::getRealClass($class);
                        $lastSeparatorPos = strrpos($realClass, "\\");
                        $secondToLastSep = strrpos(substr($realClass, 0, $lastSeparatorPos), "\\");
                        $entityName = str_replace("\\", ":", substr(
                            $realClass,
                            $secondToLastSep + 1,
                            strlen($realClass) - $secondToLastSep
                        ));

                        $attributeValue = [
                            "id"        => $attributeValue->getId(),
                            "entityName"=> $entityName,
                            "display"   => $attributeValue->__toString(),
                        ];

                    } elseif ($attributeValue instanceof \Traversable) {
                        $data = [];
                        foreach ($attributeValue as $value) {
                            if (method_exists($value, "__toString")) {
                                array_push($data, $value->__toString());
                            }
                        }
                        $attributeValue = implode(", " , $data);
                    }
                }

                if ($this->nameConverter) {
                    $attributeName = $this->nameConverter->normalize($attributeName);
                }

                $attributes[$attributeName] = $attributeValue;
            }
        }

        return $attributes;
    }

    /**
     * Denormalizes data back into an object of the given class.
     *
     * @param mixed $data data to restore
     * @param string $class the expected class to instantiate
     * @param string $format format the given data was extracted from
     * @param array $context options available to the denormalizer
     *
     * @return object
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        // TODO: Implement denormalize() method.
    }

    /**
     * Checks whether the given class is supported for denormalization by this normalizer.
     *
     * @param mixed $data Data to denormalize from
     * @param string $type The class to which the data should be denormalized
     * @param string $format The format being deserialized from
     *
     * @return bool
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        // TODO: Implement supportsDenormalization() method.
    }

    /**
     * Checks whether the given class is supported for normalization by this normalizer.
     *
     * @param mixed $data Data to normalize
     * @param string $format The format being (de-)serialized from or into
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null)
    {
        // TODO: Implement supportsNormalization() method.
    }

    /**
     * Checks if a method's name is get.* or is.*, and can be called without parameters.
     *
     * @param \ReflectionMethod $method the method to check
     *
     * @return bool whether the method is a getter or boolean getter
     */
    private function isGetMethod(\ReflectionMethod $method)
    {
        $methodLength = strlen($method->name);

        return
            !$method->isStatic() &&
            (
                ((0 === strpos($method->name, 'get') && 3 < $methodLength) ||
                    (0 === strpos($method->name, 'is') && 2 < $methodLength)) &&
                0 === $method->getNumberOfRequiredParameters()
            )
            ;
    }
}