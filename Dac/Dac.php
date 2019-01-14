<?php

namespace Maxposter\DacBundle\Dac;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Maxposter\DacBundle\Annotations\Mapping\Service\Annotations;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Dac
{
    const SQL_FILTER_NAME = 'dac_sql_filter';
    const DEFAULT_SAVE_POINT = 'dac_default_save_point';

    /* @var \Doctrine\Bundle\DoctrineBundle\Registry */
    private $doctrine;

    /** \Maxposter\DacBundle\Dac\EventSubscriber */
    private $eventSubscriber;

    /** @var \Maxposter\DacBundle\Dac\Settings */
    private $settings;

    /** @var \Maxposter\DacBundle\Annotations\Mapping\Service\Annotations */
    private $annotations;

    /** @var AuthorizationCheckerInterface */
    private $authChecker;

    /** @var bool */
    private $enabled;

    /** @var bool[] */
    private $savePoints = [];

    /** @var bool */
    private $sessionEnabled;


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
        $this->enabled = false;
        $this->savePoints[self::DEFAULT_SAVE_POINT] = false;
        $this->sessionEnabled = true;
    }


    /**
     * Включить фильтрацию
     *
     * @param string $savePoint Название точки сохранения предыдущего состояния
     */
    public function enable($savePoint = self::DEFAULT_SAVE_POINT)
    {
        if (!$this->authChecker) {
            return;
        }

        // Включение SQL-фильтра
        $filters = $this->doctrine->getManager()->getFilters(); /** @var $filters \Doctrine\ORM\Query\FilterCollection */
        $filters->enable(static::SQL_FILTER_NAME);
        $filter = $filters->getFilter(static::SQL_FILTER_NAME); /** @var $filter \Maxposter\DacBundle\Dac\SQLFilter */
        $filter->setDacSettings($this->getSettings());
        $filter->setAnnotations($this->annotations);
        $filter->setAuthorizationChecker($this->authChecker);

        $this->eventSubscriber->setDacSettings($this->getSettings());
        /** @var \Doctrine\Common\EventManager $evm */
        $evm = $this->doctrine->getManager()->getEventManager();
        $evm->addEventSubscriber($this->eventSubscriber);

        $this->savePoints[$savePoint] = $this->enabled;
        $this->enabled = true;
    }


    /**
     * Выключить фильтрацию (глобально)
     *
     * @param string $savePoint Название точки сохранения предыдущего состояния
     */
    public function disable($savePoint = self::DEFAULT_SAVE_POINT)
    {
        /** @var $filters \Doctrine\ORM\Query\FilterCollection */
        $filters = $this->doctrine->getManager()->getFilters();
        if ($filters->has(static::SQL_FILTER_NAME) && $filters->isEnabled(static::SQL_FILTER_NAME)) {
            $filters->disable(static::SQL_FILTER_NAME);
        }

        /** @var \Doctrine\Common\EventManager $evm */
        $evm = $this->doctrine->getManager()->getEventManager();
        $evm->removeEventSubscriber($this->eventSubscriber);

        $this->savePoints[$savePoint] = $this->enabled;
        $this->enabled = false;
    }


    /**
     * Восстанавливает сохраненное сотояние активности фильтров
     *
     * @param string $savePoint Название точки сохранения предыдущего состояния
     */
    public function restore($savePoint)
    {
        if (!array_key_exists($savePoint, $this->savePoints)) {
            throw new \InvalidArgumentException(sprintf('Не найдена точка сохранения "%s"', $savePoint));
        }
        if ($this->enabled === $this->savePoints[$savePoint]) {
            return;
        }
        $oldSavePoint = $this->savePoints[$savePoint];
        if ($this->savePoints[$savePoint]) {
            $this->enable($savePoint);
        } else {
            $this->disable($savePoint);
        }
        $this->savePoints[$savePoint] = $oldSavePoint;
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
     * @param  AuthorizationCheckerInterface  $authChecker
     * @return void
     */
    public function setAuthorizationChecker(AuthorizationCheckerInterface $authChecker)
    {
        $this->authChecker = $authChecker;
    }

    /**
     * @return bool
     */
    public function isSessionEnabled(): bool
    {
        return $this->sessionEnabled;
    }

    /**
     * @param bool $sessionEnabled
     * @return Dac
     */
    public function setSessionEnabled(bool $sessionEnabled): self
    {
        $this->sessionEnabled = $sessionEnabled;
        return $this;
    }


}
