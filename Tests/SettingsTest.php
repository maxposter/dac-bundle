<?php

namespace Maxposter\DacBundle\Tests;

use Maxposter\DacBundle\Dac\Settings;

class SettingsTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        $s = new Settings();

        $this->assertEquals(array(), $s->getSettings(), 'После создания ограничений нет');

        $s->setSettings(array(
            '\\MaxPoster\\Dac\\Entity\\One' => array(1),
            'MaxPoster\\Dac\\Entity\\Two\\' => array(2, 3),
            '\\MaxPoster\\Dac\\Entity\\Three\\' => array(4, 5, 6),
            'MaxPoster\\Dac\\Entity\\Four' => array(7),
        ));

        $this->assertEquals(
            array(
                'MaxPoster\\Dac\\Entity\\One' => array(1),
                'MaxPoster\\Dac\\Entity\\Two' => array(2, 3),
                'MaxPoster\\Dac\\Entity\\Three' => array(4, 5, 6),
                'MaxPoster\\Dac\\Entity\\Four' => array(7),
            ),
            $s->getSettings(),
            'В имени entity начальные и конечные \\ удаляются'
        );


        $entityName = 'MaxPoster\\Dac\\Entity\\One';
        $this->assertEquals(array(1), $s->get($entityName), 'Значения идентификаторов по имени entity вщзвращзаются');

        $newIds = array(11, 12, 13);
        $s->set($entityName, $newIds);
        $this->assertEquals($newIds, $s->get($entityName), 'Можно задать значение для конкретной entity');
        $this->assertEquals(4, count($s->getSettings()), 'Остальные значения остаются без изменений');

        $s->reset();
        $this->assertEquals(array(), $s->getSettings(), 'reset() приводит к сбросу ограничений');
    }
}