<?php

namespace Maxposter\DacBundle\Dac;

class Settings
{
    protected $settings;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * Обнуление ограничений
     */
    public function reset()
    {
        $this->settings = array();
    }

    /**
     * Задание ограничения доступа к данным
     *
     * @param $entityName   Полное название entity с неймспейсом, но без ведущего \
     * @param $ids          Допустимые идентификаторы
     */
    public function set($entityName, array $ids)
    {
        $this->settings[$this->clearEntityName($entityName)] = $ids;
    }

    /**
     * Получение идентификаторов, ограничивающих доступ к данным, для entity
     *
     * @param $entityName   Полное название entity с неймспейсом, но без ведущего \
     * @return mixed        Массив идентификаторов, либо null если по entity нет фильтрации
     */
    public function get($entityName)
    {
        $entityName = $this->clearEntityName($entityName);

        return isset($this->settings[$entityName]) ? $this->settings[$entityName] : null;
    }

    /**
     * Получение всех ограничений доступа к данным
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Задание ограничений доступа к данным
     *
     * @param array $settings   Ограничения, массив должен иметь структуру ($entityName => array(ids))
     */
    public function setSettings(array $settings)
    {
        $this->reset();
        foreach ($settings as $entityName => $ids) {
            $this->set($entityName, $ids);
        }
    }

    protected function clearEntityName($entityName)
    {
        if ('\\' === $entityName[0]) {
            $entityName = substr($entityName, 1);
        }
        if ('\\' === substr($entityName, -1)) {
            $entityName = substr($entityName, 0, -1);
        }

        return $entityName;
    }
}