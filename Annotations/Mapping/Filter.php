<?php
namespace Maxposter\DacBundle\Annotations\Mapping;

use Doctrine\ORM\Mapping\Annotation;

/**
 * Dac - аннотации
 * синтаксис: @NS\Filter(targetEntity="Some\Class\Name")
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class Filter implements Annotation
{
    /** @var string */
    public $targetEntity;
}