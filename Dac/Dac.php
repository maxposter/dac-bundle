<?php

namespace Maxposter\DacBundle\Dac;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Maxposter\DacBundle\Annotations\Mapping\Service\Annotations;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;

class Dac
{
    const SQL_FILTER_NAME = 'dac_sql_filter';

    /* @var \Doctrine\Bundle\DoctrineBundle\Registry */
    private $doctrine;

    /** \Maxposter\DacBundle\Dac\EventSubscriber */
    private $eventSubscriber;

    /** @var \Maxposter\DacBundle\Dac\Settings */
    private $settings;

    /** @var \Maxposter\DacBundle\Annotations\Mapping\Service\Annotations */
    private $annotations;

    /** @var \Symfony\Component\Security\Core\SecurityContextInterface */
    private $security;


    /**
     * @param Registry        $doctrine
     * @param EventSubscriber $eventSubscriber
     * @param Annotations     $annotations
     */
    public function __construct(Registry $doctrine, EventSubscriber $eventSubscriber, Annotations $annotations)
    {
        $this->doctrine = $doctrine;
        $this->eventSubscriber = $eventSubscriber;

        // Регистрация sql-фильтра, чтобы в коде можно было ссылаться на его имя
        /** @var $m \Doctrine\ORM\EntityManager */
        $manager = $this->doctrine->getManager();
        $manager->getConfiguration()->addFilter(
            static::SQL_FILTER_NAME, 'Maxposter\\DacBundle\\Dac\\SqlFilter'
        );
        $this->annotations = $annotations;
    }


    /**
     * Включить фильтрацию
     */
    public function enable()
    {
        if (!$this->security) {
            return;
        }

        // Включение SQL-фильтра
        $filters = $this->doctrine->getManager()->getFilters(); /** @var $filters \Doctrine\ORM\Query\FilterCollection */
        $filters->enable(static::SQL_FILTER_NAME);
        $filter = $filters->getFilter(static::SQL_FILTER_NAME); /** @var $filter \Maxposter\DacBundle\Dac\SQLFilter */
        $filter->setDacSettings($this->getSettings());
        $filter->setAnnotations($this->annotations);
        $filter->setSecurityContext($this->security);

        $this->eventSubscriber->setDacSettings($this->getSettings());
        /** @var \Doctrine\Common\EventManager $evm */
        $evm = $this->doctrine->getManager()->getEventManager();
        $evm->addEventSubscriber($this->eventSubscriber);
    }


    /**
     * Выключить фильтрацию (глобально)
     */
    public function disable()
    {
        /** @var $filters \Doctrine\ORM\Query\FilterCollection */
        $filters = $this->doctrine->getManager()->getFilters();
        if ($filters->has(static::SQL_FILTER_NAME) && $filters->isEnabled(static::SQL_FILTER_NAME)) {
            $filters->disable(static::SQL_FILTER_NAME);
        }

        /** @var \Doctrine\Common\EventManager $evm */
        $evm = $this->doctrine->getManager()->getEventManager();
        $evm->removeEventSubscriber($this->eventSubscriber);
    }


    /**
     * @param Settings $settings
     */
    public function setSettings(Settings $settings)
    {
        $this->settings = $settings;
    }


    /**
     * @return Settings
     */
    protected function getSettings()
    {
        if (is_null($this->settings)) {
            $this->settings = new Settings();
        }
        return $this->settings;
    }


    /**
     * Безопасность
     *
     * @param  SecurityContextInterface  $security
     * @return void
     */
    public function setSecurityContext(SecurityContextInterface $security)
    {
        $this->security = $security;
    }

}
