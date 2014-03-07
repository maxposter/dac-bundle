<?php
namespace Maxposter\DacBundle\Annotations\Mapping;

use Doctrine\ORM\Mapping\Annotation;

/**
 * Dac - аннотации
 * синтаксис: @NS\Filter(targetEntity="Some\Class\Name", role="ROLE_ADMIN")
 * - targetEntity можно не указывать, если есть связь
 * - role определяет администратора, для которого фильтры работают иначе:
 *      * в выборках (SELECT) доступно всё
 *      * при INSERT/UPDATE/DELETE чужие значения не заменяются
 *        на "свои", но подставляются если не заданы
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
final class Filter implements Annotation
{
    /** @var string */
    public $targetEntity;

    /** @var array<string> */
    public $role;
}