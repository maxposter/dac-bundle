<?php
namespace Maxposter\DacBundle\Dac;

use Doctrine\ORM\EntityManager;
use Maxposter\DacBundle\Annotations\Mapping\Service\Annotations;
use Maxposter\DacBundle\Dac\Dac;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class SettingsListener
{
    private $dac;
    private $annotations;
    private $security;
    private $authChecker;
    private $tokenStorage;
    private $session;
    private $provider;

    public function __construct(Dac $dac, Annotations $annotations, AuthorizationCheckerInterface $authChecker, TokenStorageInterface $tokenStorage, SessionInterface $session, SettingsProviderInterface $provider)
    {
        $this->dac          = $dac;
        $this->annotations  = $annotations;
        $this->authChecker  = $authChecker;
        $this->tokenStorage = $tokenStorage;
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

        if (!$this->dac->isSessionEnabled()) {
            $params = $this->loadDacSettings();
        } elseif ($this->session->has('maxposter.dac.settings')) {
            $params = $this->session->get('maxposter.dac.settings', array());
        } else {
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
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            // FIXME: в веб-дебаг тулбаре нет токена (если веб-дебаг на другом (пустом) файрволе), как следствие нельзя чистить сессию
            return;
        }

        if ($this->dac->isSessionEnabled()) {
            if ($token->isAuthenticated() && (!$this->authChecker->isGranted('IS_AUTHENTICATED_REMEMBERED'))) {
                $this->session->remove('maxposter.dac.user');
                $this->session->remove('maxposter.dac.dealerId');
                $this->session->remove('maxposter.dac.holdingId');
                $this->session->remove('maxposter.dac.distributorId');
                $this->session->remove('maxposter.dac.settings');
                return;
            }

            if ($token->getUsername() != $this->session->get('maxposter.dac.user', '')) {
                $this->session->remove('maxposter.dac.settings');
                $this->session->remove('maxposter.dac.dealerId');
                $this->session->remove('maxposter.dac.holdingId');
                $this->session->remove('maxposter.dac.distributorId');
                $this->session->set('maxposter.dac.user', $token->getUsername());
            }

            if (
                $token->getUser() &&
                (
                    $token->getUser()->getCurrentDealerId() != $this->session->get('maxposter.dac.dealerId', '') ||
                    $token->getUser()->getCurrentHoldingId() != $this->session->get('maxposter.dac.holdingId', '') ||
                    $token->getUser()->getCurrentDistributorId() != $this->session->get('maxposter.dac.distributorId', '')
                )
            ) {
                $this->session->remove('maxposter.dac.settings');
                $this->session->set('maxposter.dac.dealerId', $token->getUser()->getCurrentDealerId());
                $this->session->set('maxposter.dac.holdingId', $token->getUser()->getCurrentHoldingId());
                $this->session->set('maxposter.dac.distributorId', $token->getUser()->getCurrentDistributorId());
            }
        }

        $this->dac->setSettings($this->makeDacSettings());
        $this->dac->setAuthorizationChecker($this->authChecker);
        $this->dac->enable();
    }


    /**
     * @param \Symfony\Component\Security\Http\Event\SwitchUserEvent $event
     */
    public function onSecuritySwitchUser(\Symfony\Component\Security\Http\Event\SwitchUserEvent $event)
    {
        if ($this->dac->isSessionEnabled()) {
            $this->session->remove('maxposter.dac.user');
            $this->session->remove('maxposter.dac.dealerId');
            $this->session->remove('maxposter.dac.holdingId');
            $this->session->remove('maxposter.dac.distributorId');
            $this->session->remove('maxposter.dac.settings');
        }

        $this->dac->setSettings($this->makeDacSettings());
        $this->dac->setAuthorizationChecker($this->authChecker);
        $this->dac->enable();
    }
}