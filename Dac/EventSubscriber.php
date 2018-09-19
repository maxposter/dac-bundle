<?php
namespace Maxposter\DacBundle\Dac;

use Doctrine\Common\EventSubscriber as EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\Events;
/* use Doctrine\Common\Persistence\Event\LifecycleEventArgs; */
/* use Doctrine\ORM\Event\LifecycleEventArgs; */
/* use Doctrine\ORM\Event\PreFlushEventArgs; */
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Proxy\Proxy as DoctrineProxy;

use Maxposter\DacBundle\Annotations\Mapping\Service\Annotations;
use Maxposter\DacBundle\Dac\Exception\Event as EventException;

/**
 * Class EventSubscriber
 * @package Maxposter\DacBundle\Dac
 */
class EventSubscriber implements EventSubscriberInterface
{
    /** @var \Symfony\Component\DependencyInjection\ContainerInterface  */
    private $container;
    /** @var TokenStorageInterface */
    private $tokenStorage;
    /** @var AuthorizationCheckerInterface */
    private $authChecker;
    /** @var \Maxposter\DacBundle\Annotations\Mapping\Service\Annotations */
    private $annotations;
    /** @var \Maxposter\DacBundle\Dac\Settings */
    private $dacSettings;
    /** @var \Doctrine\ORM\EntityManager */
    private $em;


    /**
     * Конструктор
     *
     * @param  \Symfony\Component\DependencyInjection\ContainerInterface  $container
     */
    public function __construct(ContainerInterface $container)
    {
        // @see http://stackoverflow.com/questions/8708822/circular-reference-when-injecting-security-context-into-entity-listener-class
        $this->container = $container;
        //$this->annotations = $annotations;
    }


    /**
     * Настройки
     *
     * @param  \Maxposter\DacBundle\Dac\Settings  $settings
     */
    public function setDacSettings(Settings $settings)
    {
        $this->dacSettings = $settings;
    }


    /**
     * Настройки
     *
     * @return \Maxposter\DacBundle\Dac\Settings|false
     * @throws Exception
     */
    private function getDacSettings()
    {
        if (is_null($this->dacSettings)) {
            return false;
        }

        return $this->dacSettings;
    }


    /**
     * Аннотации
     *
     * @return \Maxposter\DacBundle\Annotations\Mapping\Service\Annotations
     */
    private function getAnnotations()
    {
        if (!$this->annotations) {
            $this->annotations = $this->container->get('maxposter.dac.annotations');
        }

        return $this->annotations;
    }

    /**
     * @return TokenStorageInterface
     */
    private function getTokenStorage()
    {
        if (!$this->tokenStorage) {
            $this->tokenStorage = $this->container->get('security.token_storage');
        }

        return $this->tokenStorage;
    }

    /**
     * @return AuthorizationCheckerInterface
     */
    private function getAuthorizationChecker()
    {
        if (!$this->authChecker) {
            $this->authChecker = $this->container->get('security.authorization_checker');
        }

        return $this->authChecker;
    }


    private function isGranted(array $roles)
    {
        $ret = true;
        if ($this->getTokenStorage()->getToken()) {
            $ret = $this->getAuthorizationChecker()->isGranted($roles);
        }

        return $ret;
    }


    /**
     * Проверить доступность значения
     *
     * @param  string   $dacSettingsName
     * @param  integer  $value
     * @return bool
     */
    private function isValid($dacSettingsName, $value)
    {
        if (!$this->getDacSettings()) {
            return true;
        }

        $dacSettingsValue = $this->getDacSettings()->get($dacSettingsName);

        if (is_array($value)) {
            return ($dacSettingsValue && $value && !array_diff($value, $dacSettingsValue));
        } else {
            return ($dacSettingsValue && in_array($value, $dacSettingsValue));
        }
    }


    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
        );
    }


    /**
     * Получить значение из Entity
     *
     * @param  object $entity
     * @param  string $property
     * @return mixed
     */
    private function getEntityValue($entity, $property)
    {
        $classMetadata = $this->em->getClassMetadata(get_class($entity));

        return $classMetadata->getReflectionProperty($property)->getValue($entity);
    }


    /**
     * Проверить, что фильтруемое поле не содержит значения
     *
     * @param  object $entity
     * @param  string $property
     * @return bool
     */
    private function isEmptyEntityValue($entity, $property)
    {
        $originalValue = $this->getEntityValue($entity, $property);

        return (
            (null === $originalValue)
            || (
                ($originalValue instanceof Collection)
                && (0 === $originalValue->count())
            )
        );
    }


    /**
     * Получить единственное значение, если возможно
     *
     * @param  string $dacSettingsName
     * @return integer|null
     */
    private function getSingleSettingsValue($dacSettingsName)
    {
        if (!$this->getDacSettings()) {
            return null;
        }

        $dacSettingsValue = $this->getDacSettings()->get($dacSettingsName);
        if ($dacSettingsValue && (1 === count($dacSettingsValue))) {
            $value = array_shift($dacSettingsValue);

            return $value;
        }

        return null;
    }


    /**
     * Получить значение для Entity из настроек Dac
     *
     * @param  object  $entity
     * @param  string  $property
     * @param  string  $dacSettingsName
     * @throws Exception\Event\NoSingleValueException
     * @throws Exception
     * @return Collection|int|object
     */
    private function getEntityValueFromDac($entity, $property, $dacSettingsName)
    {
        $em = $this->em;
        $classMetadata = $em->getClassMetadata(get_class($entity));
        // Пытаемся получить значение для подстановки
        /** @var integer $value */
        if (null === ($value = $this->getSingleSettingsValue($dacSettingsName))) {
            throw new EventException\NoSingleValueException(sprintf('Невозможно получить единственно верное значение для поля %s в %s', $property, $classMetadata->getName()));
        }

        // Свойство сущности - объект
        if ($classMetadata->hasAssociation($property)) {
            $assocMapping = $classMetadata->getAssociationMapping($property);
            if (ClassMetadata::ONE_TO_MANY === $assocMapping['type']) {
                // FIXME: запретить устанавливать аннотации
                throw new Exception(sprintf('Связь один-ко-много не может обрабатываться, %s в %s', $property, $classMetadata->getName()));
            }
            // Для много-ко-много
            if (ClassMetadata::MANY_TO_MANY === $assocMapping['type']) {
                // Пример: ManyToOne всегда owningSide
                /** @see http://docs.doctrine-project.org/en/latest/reference/unitofwork-associations.html */
                if ($assocMapping['isOwningSide']) {
                    $element = $em->getReference($classMetadata->getAssociationTargetClass($property), $value);
                    /** @var \Doctrine\Common\Collections\Collection $value */
                    $value = $classMetadata->getReflectionProperty($property)->getValue($entity);
                    $value->add($element);
                } else {
                    // FIXME: запрещать устанавливать аннотации
                    throw new Exception(sprintf('Связь много-ко-много может обрабатываться только с зависимой стороны, %s в %s', $property, $classMetadata->getName()));
                }
                // Для *-к-одному получаем объект
            } else {
                /** @var object $value */
                $value = $em->getReference($classMetadata->getAssociationTargetClass($property), $value);
            }
        }

        return $value;
    }


    /**
     * Получить значение из Entity для сравнения с Dac
     *
     * @param $entity
     * @param $property
     * @return array|mixed
     * @throws Exception
     * @throws Exception\Event\ParentNotPersistedException
     */
    private function getEntityColumnValue($entity, $property)
    {
        $em = $this->em;
        $classMetadata = $em->getClassMetadata(get_class($entity));

        $value = $this->getEntityValue($entity, $property);
        if ($classMetadata->hasAssociation($property)) {
            $assocMapping = $classMetadata->getAssociationMapping($property);
            if (ClassMetadata::ONE_TO_MANY === $assocMapping['type']) {
                // FIXME: тест
                throw new Exception(sprintf('Связь один-ко-много не может обрабатываться, %s в %s', $property, $classMetadata->getName()));
            }
            // Для много-ко-много
            if (ClassMetadata::MANY_TO_MANY === $assocMapping['type']) {
                // Пример: ManyToOne всегда owningSide
                /** @see http://docs.doctrine-project.org/en/latest/reference/unitofwork-associations.html */
                if ($assocMapping['isOwningSide']) {
                    $assocMetadata = $em->getClassMetadata($classMetadata->getAssociationTargetClass($property));
                    $columnName = $assocMapping['joinTable']['inverseJoinColumns']['0']['referencedColumnName'];
                    $reflectionProp = $assocMetadata->getReflectionProperty($assocMetadata->getFieldName($columnName));
                    $values = array();
                    foreach ($value as $assocEntity) {
                        if (UnitOfWork::STATE_NEW === $em->getUnitOfWork()->getEntityState($assocEntity, UnitOfWork::STATE_NEW)) {
                            throw new EventException\ParentNotPersistedException(sprintf('Родительские сущности должны быть сохранены, %s в %s', $property, $classMetadata->getName()));
                        }
                        $values[] = $reflectionProp->getValue($assocEntity);
                    }
                    $value = $values;
                } else {
                    // FIXME: test
                    throw new Exception(sprintf('Связь много-ко-много может обрабатываться только с зависимой стороны, %s в %s', $property, $classMetadata->getName()));
                }
            // Для *-к-одному получаем объект
            } else {
                $assocMetadata = $em->getClassMetadata($classMetadata->getAssociationTargetClass($property));
                $columnName = $assocMapping['joinColumns']['0']['referencedColumnName'];
                $fieldName = $assocMetadata->getFieldForColumn($columnName);
                $reflectionProp = $assocMetadata->getReflectionProperty($fieldName);
                // Для проверки нужно получить идентификатор из связанной (родительской) записи
                $value = $reflectionProp->getValue($value);
                if (is_object($value) && $assocMetadata->hasAssociation($fieldName)) {
                    $value = $this->getEntityColumnValue($value, $assocMetadata->getAssociationMapping($fieldName)['joinColumns']['0']['referencedColumnName']);
                }
            }
        }

        return $value;
    }


    private function processEntityInsertions()
    {
        $em = $this->em;
        $annotations = $this->getAnnotations();
        foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            $className = get_class($entity);
            if (!$annotations->hasDacFields($className)) {
                continue;
            }

            $recomputeChangeSet = $computeChangeSet = false;

            $classMetadata = $em->getClassMetadata($className);
            foreach ($annotations->getDacFields($classMetadata->getName()) as $filteredFieldName => $dacSettingsName) {
                $roles = $annotations->getDacRolesFor($className, $filteredFieldName);
                if (!empty($roles) && $this->isGranted($roles)) {
                    continue;
                }

                // Пропускаем PrimaryKey
                if ($filteredFieldName == $classMetadata->getSingleIdentifierColumnName()) {
                    continue;
                }

                // Необходимо установить значение поля
                if ($this->isEmptyEntityValue($entity, $filteredFieldName)) {
                    $value = $this->getEntityValueFromDac($entity, $filteredFieldName, $dacSettingsName);
                    $classMetadata->getReflectionProperty($filteredFieldName)->setValue($entity, $value);
                    $recomputeChangeSet = true;
                    if (is_object($value) && ($value instanceof Collection)) {
                        $computeChangeSet = true;
                    }
                } else {
                    $value = $this->getEntityColumnValue($entity, $filteredFieldName);

                    if (!$this->isValid($dacSettingsName, $value)) {
                        throw new EventException\WrongValueException(sprintf('Неверное значение поля %s в %s', $filteredFieldName, $classMetadata->getName()));
                    }
                }
            }

            if ($computeChangeSet) {
                // FIXME: отмечен как @internal, но по другому не работает для many-to-many
                $em->getUnitOfWork()->computeChangeSet($classMetadata, $entity);
            } elseif ($recomputeChangeSet) {
                $em->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
            }
        }
    }


    private function processEntityUpdates()
    {
        $em = $this->em;
        $annotations = $this->getAnnotations();
        foreach ($em->getUnitOfWork()->getScheduledEntityUpdates() as $entity) {
            $className = get_class($entity);
            if (!$annotations->hasDacFields($className)) {
                continue;
            }

            $recomputeChangeSet = $computeChangeSet = false;

            $classMetadata = $em->getClassMetadata($className);
            foreach ($annotations->getDacFields($className) as $filteredFieldName => $dacSettingsName) {
                $roles = $annotations->getDacRolesFor($className, $filteredFieldName);
                if (!empty($roles) && $this->isGranted($roles)) {
                    continue;
                }

                // primaryKey не пропускаем, т.к. есть значения
                // Необходимо установить значение поля
                if ($this->isEmptyEntityValue($entity, $filteredFieldName)) {
                    $value = $this->getEntityValueFromDac($entity, $filteredFieldName, $dacSettingsName);
                    $classMetadata->getReflectionProperty($filteredFieldName)->setValue($entity, $value);
                    $recomputeChangeSet = true;
                    if (is_object($value) && ($value instanceof Collection)) {
                        $computeChangeSet = true;
                    }
                } else {
                    $value = $this->getEntityColumnValue($entity, $filteredFieldName);

                    if (!$this->isValid($dacSettingsName, $value)) {
                        throw new EventException\WrongValueException(sprintf('Неверное значение поля %s в %s', $filteredFieldName, $classMetadata->getName()));
                    }
                }
            }

            if ($computeChangeSet) {
                // FIXME: отмечен как @internal, но по другому не работает для many-to-many
                $em->getUnitOfWork()->computeChangeSet($classMetadata, $entity);
            } elseif ($recomputeChangeSet) {
                $em->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
            }
        }
    }


    private function processEntityDeletions()
    {
        $em = $this->em;
        $annotations = $this->getAnnotations();
        foreach ($em->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            $className = get_class($entity);
            if (!$annotations->hasDacFields($className)) {
                continue;
            }

            $classMetadata = $em->getClassMetadata($className);
            foreach ($annotations->getDacFields($className) as $filteredFieldName => $dacSettingsName) {
                $roles = $annotations->getDacRolesFor($className, $filteredFieldName);
                if (!empty($roles) && $this->isGranted($roles)) {
                    continue;
                }

                $value = $this->getEntityColumnValue($entity, $filteredFieldName);

                if (!$this->isValid($dacSettingsName, $value)) {
                    throw new EventException\WrongValueException(sprintf('Неверное значение поля %s в %s', $filteredFieldName, $classMetadata->getName()));
                }
            }
        }
    }


    public function onFlush(OnFlushEventArgs $args)
    {
        // Ловим EntityManager
        $this->em = $em = $args->getEntityManager();

        // INSERT
        $this->processEntityInsertions();

        // UPDATE
        $this->processEntityUpdates();

        // DELETE
        $this->processEntityDeletions();

        // После обновление до Doctrine 2.5 сюда стали поподатья коллекции из связанных сущностей, пока выпиливаем
//        $uow = $em->getUnitOfWork();
//        foreach ($uow->getScheduledCollectionDeletions() as $col) {
//            // FIXME: надо понять как это (коллекции) работает
//            throw new \Exception('getScheduledCollectionDeletions не обрабатывается в ' . __CLASS__);
//        }
//
//        foreach ($uow->getScheduledCollectionUpdates() as $col) {
//            throw new \Exception('getScheduledCollectionUpdates не обрабатывается в ' . __CLASS__);
//        }
    }
}
