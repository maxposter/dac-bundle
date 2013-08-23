<?php
namespace Maxposter\DacBundle\Dac;

use Doctrine\ORM\EntityManager;
use Maxposter\DacBundle\Annotations\Mapping\Service\Annotations;
use Maxposter\DacBundle\Dac\Dac;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class SettingsListener
{
    private $dac;
    private $annotations;
    private $security;
    private $session;
    private $provider;

    public function __construct(Dac $dac, Annotations $annotations, SecurityContextInterface $security, SessionInterface $session, SettingsProviderInterface $provider)
    {
        $this->dac          = $dac;
        $this->annotations  = $annotations;
        $this->security     = $security;
        $this->session      = $session;
        $this->provider     = $provider;
    }


    private function loadDacSettings()
    {
        return $this->provider->load();
    }


    private function makeDacSettings()
    {
        $settings = new Settings();

        if ($this->session->has('maxposter.dac.settings')) {
            var_dump('has session data');
            $params = $this->session->get('maxposter.dac.settings', array());
        } else {
            var_dump('need to load data');
            $params = $this->loadDacSettings();
            $this->session->set('maxposter.dac.settings', $params);
        }
        $settings->setSettings($params);

        return $settings;
    }


    public function onAuthorization(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        // Обрабатываем только авторизованных (в человеческом понимании) пользователей
        $token = $this->security->getToken();
        if ($token && $token->isAuthenticated() && (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED'))) {
            return;
        }
        var_dump('Go next');

        $this->dac->setSettings($this->makeDacSettings());
        $this->dac->enable();
    }
}