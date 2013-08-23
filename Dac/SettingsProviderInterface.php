<?php
namespace Maxposter\DacBundle\Dac;

interface SettingsProviderInterface
{
    /**
     * @return array
     */
    function load();
}