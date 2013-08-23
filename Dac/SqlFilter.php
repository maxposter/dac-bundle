<?php
namespace Maxposter\DacBundle\Dac;

use \Doctrine\ORM\Mapping\ClassMetadata;
use \Doctrine\DBAL\Connection;
use Maxposter\DacBundle\Annotations\Mapping\Service\Annotations;

/**
 * @package Maxposter\DacBundle\SqlFilter
 */
class SqlFilter extends \Doctrine\ORM\Query\Filter\SQLFilter
{

    private
        /** @var Settings */
        $dacSettings,

        /** @var Annotations */
        $annotations
    ;

    /**
     * Установка объекта со значениями DAC-аннотаций
     *
     * @param Annotations $annotations
     */
    public function setAnnotations(Annotations $annotations)
    {
        $this->annotations = $annotations;
    }

    /**
     * Получение объекта со значениями DAC-аннотаций
     *
     * @return Annotations
     * @throws Exception
     * fixme: Нужен тест
     */
    public function getAnnotation()
    {
        if (is_null($this->annotations)) {
            throw new Exception('Ошибка в инициализации SQL-фильтра: не задан объект с аннотациями.', Exception::ERR_SQL_FILTER);
        }

        return $this->annotations;
    }

    /**
     * Установка контейнера с параметрами фильтрации данных
     *
     * @param Settings $dacSettings
     */
    public function setDacSettings(Settings $dacSettings)
    {
        $this->dacSettings = $dacSettings;
    }

    /**
     * Получение контейнера с параметрами фильтрации данных
     *
     * @return Settings
     * @throws Exception
     * fixme: Нужен тест
     */
    public function getDacSettings()
    {
        if (is_null($this->dacSettings)) {
            throw new Exception('Ошибка в инициализации SQL-фильтра: не заданы параметры фильтрации.', Exception::ERR_SQL_FILTER);
        }

        return $this->dacSettings;
    }

    /**
     * Модификация SQL-запроса
     *
     * @param  \Doctrine\ORM\Mapping\ClassMetadata $targetEntity
     * @param  string $targetTableAlias
     * @return string The constraint SQL if there is available, empty string otherwise
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        $entityName = $targetEntity->getReflectionClass()->getName();

        $annotation = $this->getAnnotation();
        if (!$annotation->hasDacFields($entityName)) {
            return '';
        }

        $dacFields = $annotation->getDacFields($entityName);
        $conditions = array();
        foreach ($dacFields as $filteredFieldName => $dacSettingsName) {
            $filteredColumnName = $this->getColumnName($filteredFieldName, $targetEntity);
            $dacSettingsValue   = $this->getDacSettings()->get($dacSettingsName);
            if ($dacSettingsValue) {
                $conditions[] = sprintf(
                    '%s.%s IN (\'%s\')',
                    $targetTableAlias,
                    $filteredColumnName,
                    implode('\', \'', (array)$dacSettingsValue)
                );
            }
        }

        if ($conditions) {
            $result = sprintf('((%s))', implode(') OR (', $conditions));
        } else {
            $result = '1=2';
        }

        return $result;
    }

    /**
     * Получение названия поля в таблице в БД по названию параметра в сущности
     *
     * @param string $fieldName     Название параметра в сущности
     * @param ClassMetadata $targetEntity
     * @return string Искомое       Название поля в БД
     * @throws Exceptions
     * fixme: Нужны тесты на разное поведение метода
     */
    private function getColumnName($fieldName, ClassMetadata $targetEntity)
    {
        if (in_array($fieldName, $targetEntity->getFieldNames())) {
            $columnName =  $targetEntity->getColumnName($fieldName);
        } elseif (in_array($fieldName, $targetEntity->getAssociationNames())) {
            $associationMappings = $targetEntity->associationMappings;

            if (count($associationMappings[$fieldName]['joinColumnFieldNames']) !== 1) {
                throw new Exception(sprintf('Для свойства %s связь реализована по составному ключу. Такое поведение не поддерживается DAC.', $fieldName), Exception::ERR_SQL_FILTER);
            }
            $columnName = array_pop($associationMappings[$fieldName]['joinColumnFieldNames']);
        } else {
            throw new Exception(sprintf('ClassMetadata не содержит информацию о названии столбца в таблице для свойства %s.', $fieldName), Exception::ERR_SQL_FILTER);
        }

        return $columnName;
    }
}