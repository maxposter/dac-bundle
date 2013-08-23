<?php
namespace Maxposter\DacBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Maxposter\DacBundle\Dac\Dac;

class DacTest extends WebTestCase
{
    public function testEnabledAndDisable()
    {
        $client = static::createClient($options = array(), $serverArgs = array());
        $dac = $client->getContainer()->get('maxposter.dac.dac');

        /** @var $filters \Doctrine\ORM\Query\FilterCollection */
        $filters = $client->getContainer()->get('doctrine')->getManager()->getFilters();

        $this->assertArrayNotHasKey(Dac::SQL_FILTER_NAME, $filters->getEnabledFilters(), 'Изначально фильтр объявлен но не включен');

        $dac->enable();
        $this->assertArrayHasKey(Dac::SQL_FILTER_NAME, $filters->getEnabledFilters(), 'При включении DAC фильтр активизируется');

        $dac->disable();
        $this->assertArrayNotHasKey(Dac::SQL_FILTER_NAME, $filters->getEnabledFilters(), 'При отключении DAC фильтр деактивируется');
    }
}