<?php
namespace Maxposter\DacBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use \Symfony\Bundle\FrameworkBundle\Client;
use \Doctrine\Orm\EntityManager;
use \Doctrine\ORM\Mapping\ClassMetadata;
use \Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
use \Maxposter\DacBundle\Dac\Dac;
use \Maxposter\DacBundle\Dac\Settings;
use \Maxposter\DacBundle\Dac\SqlFilter;
use \Maxposter\DacBundle\Annotations\Mapping\Service\Annotations;

class SqlFilterTest extends WebTestCase
{
    /**
     * @return array
     */
    protected function setUpAddFilterConstraint(Settings $dacSettings)
    {
        $client = static::createClient($options = array(), $serverArgs = array());
        $doctrine = $client->getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $mainEntity = new MainEntity();
        $linkedEntity = new TrickyEntity();

        $annotations = $this->getMock('\Maxposter\DacBundle\Annotations\Mapping\Service\Annotations', array('hasDacFields', 'getDacFields'), array(), '', false);
        $annotations->expects($this->any())
            ->method('hasDacFields')
            ->will($this->returnValue(true));
        $annotations->expects($this->any())
            ->method('getDacFields')
            ->will($this->returnValue(array(
                'id' => get_class($mainEntity),
                'key' => get_class($linkedEntity)
            )));

        $dac = $client->getContainer()->get('maxposter.dac.dac');
        $dac->setSettings($dacSettings);
        $dac->enable();

        /** @var $filters \Doctrine\ORM\Query\FilterCollection */
        $filter = $em->getFilters()->getFilter(Dac::SQL_FILTER_NAME);
        $filter->setAnnotations($annotations);

        $meta = new ClassMetadata(get_class($mainEntity), $em->getConfiguration()->getNamingStrategy());
        $meta->initializeReflection(new RuntimeReflectionService());
        $meta->identifier[0] = 'id';
        $meta->fieldMappings = array(
            'id'    => 'id',
            'key'   => 'tricky_key'
        );

        return array(
            'filter'        => $filter,
            'annotation'    => $annotations,
            'meta'          => $meta
        );
    }

    /**
     * Сущность фильтруется, но у пользователя нет ни одного параметра для фильтрации.
     * В запрос намеренно добавляется ложное условие, чтобы получить пустую выборку.
     */
    public function testAddFilterConstraint_Empty_dacSettings()
    {
        $dacSettings = new Settings();

        /**
         * @var SqlFilter $filter
         * @var ClassMetadata $meta
         */
        extract($this->setUpAddFilterConstraint($dacSettings));

        $this->assertEquals('1=2', $filter->addFilterConstraint($meta, 'a_'));
    }

    /**
     * Сущность фильтруется, условие по ключу
     */
    public function testAddFilterConstraint_Filtered_By_Id()
    {
        $dacSettings = new Settings();
        $dacSettings->set(__NAMESPACE__.'\\MainEntity', array(24, 36));

        /**
         * @var SqlFilter $filter
         * @var ClassMetadata $meta
         */
        extract($this->setUpAddFilterConstraint($dacSettings));

        $this->assertEquals('((a_.id IN (\'24\', \'36\')))', $filter->addFilterConstraint($meta, 'a_'));
    }

    /**
     * Вызвать private|protected метод
     *
     * @param  object $obj
     * @param  string $name
     * @return mixed
     */
    protected function callMethod($obj, $name)
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        $args = func_get_args();
        array_shift($args);
        array_shift($args);

        return $method->invokeArgs($obj, $args);
    }
}

class MainEntity {};
class TrickyEntity {};