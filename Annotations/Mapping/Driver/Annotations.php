<?php
namespace Maxposter\DacBundle\Annotations\Mapping\Driver;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\MappingException;

class Annotations
{
    private $em;
    private $reader;

    public function __construct(Reader $reader, EntityManager $em)
    {
        $this->reader = $reader;
        $this->em     = $em;
    }


    public function getAnnotatedColumns($className)
    {
        $meta = $this->em->getClassMetadata($className);
        $props = $meta->getReflectionProperties();
        $result = array();
        /** @var  $prop  \ReflectionProperty */
        foreach ($props as $prop) {
            /** @var  $annotation  \Maxposter\DacBundle\Annotations\Mapping\Filter */
            $annotation = $this->reader->getPropertyAnnotation(
                $prop,
                'Maxposter\DacBundle\Annotations\Mapping\Filter'
            );
            if (null !== $annotation) {
                // Если не задано значение
                if (empty($annotation->targetEntity)) {
                    if ($meta->getSingleIdentifierColumnName() == $prop->getName()) {
                        $target = $className;
                    } elseif ($meta->hasAssociation($prop->getName())) {
                        $mapping = $meta->getAssociationMapping($prop->getName());
                        $target = $mapping['targetEntity'];
                    } else {
                        throw MappingException::missingTargetEntity($prop->getName());
                    }
                } else {
                    if (false === strpos('\\', $annotation->targetEntity)) {
                        $nameParts = explode('\\', $meta->getName());
                        array_pop($nameParts);
                        $annotation->targetEntity = sprintf('%s\\%s', implode('\\', $nameParts), $annotation->targetEntity);
                    }

                    $target = $annotation->targetEntity;
                }
                $result[$prop->getName()] = $target;
            }
        }

        return $result;
    }
}