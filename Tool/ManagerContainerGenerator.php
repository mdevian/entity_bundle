<?php
namespace Wikimart\EntityBundle\Tool;


/**
 * Class ManagerContainerGenerator
 *
 * @package Wikimart\EntityBundle\Tool
 * @author  devian
 */
class ManagerContainerGenerator
{
    private $managers;
    private $bundle;
    private $type;

    private $spaces = '    ';

    private $containerTemplate = '<?php
namespace <bundle>\Configurator;

<use>

class <type>ManagerContainer
{
<fields>

    public function __construct(
<arguments>
    ) {
<assignment>
    }

<getters>
}';

    private $factoryTemplate = '<?php
namespace <bundle>\Configurator;

use Doctrine\Bundle\DoctrineBundle\Registry;
<use>
use Doctrine\Common\Persistence\ObjectManager;

class <type>ManagerContainerFactory
{
    private $doctrine;
<fields>

    private $containers = [];

    public function __construct(
        Registry $doctrine,
<arguments>
    ) {
        $this->doctrine = $doctrine;
<assignment>
    }

    /**
     * @param string $emName
     *
     * @return <type>ManagerContainer
     */
<factory>
}';

    private $getterTemplate = '
    /**
     * @return <manager>
     */
    public function get<manager>()
    {
        return $this-><lcfmanager>;
    }
    ';

    private $factoryMethodTemplate = '
    public function getManagerContainer($emName = \'default\')
    {
        if (!isset($this->containers[$emName])) {
             /**
             * @var ObjectManager $em
             */
            $em = $this->doctrine->getManager($emName);

            $this->containers[$emName] = new <type>ManagerContainer(
<setters>
            );
        }

        return $this->containers[$emName];
    }';

    public function __construct(array $managers, $bundle, $type)
    {
        $this->managers = $managers;
        $this->bundle   = $bundle;
        $this->type     = $type;
    }

    public function generateManagerContainer()
    {
        $placeholders = [
            '<bundle>',
            '<use>',
            '<type>',
            '<fields>',
            '<arguments>',
            '<assignment>',
            '<getters>',
        ];

        $replacement = [
            $this->bundle,
            $this->getUse(),
            ucfirst($this->type),
            $this->getFields(),
            $this->getArguments(),
            $this->getAssignment(),
            $this->getGetters(),
        ];

        return str_replace($placeholders, $replacement, $this->containerTemplate);
    }

    public function generateManagerContainerFactory()
    {
        $placeholders = [
            '<bundle>',
            '<use>',
            '<type>',
            '<fields>',
            '<arguments>',
            '<assignment>',
            '<factory>',
        ];

        $replacement = [
            $this->bundle,
            $this->getUse(),
            ucfirst($this->type),
            $this->getFields(),
            $this->getArguments(),
            $this->getAssignment(),
            $this->getFactory(),
        ];

        return str_replace($placeholders, $replacement, $this->factoryTemplate);
    }

    private function getUse()
    {
        $ret = '';
        foreach ($this->managers as $manager) {
            $ret .= 'use ' . $this->bundle . '\\Model\\Manager\\' . $manager . ';' . PHP_EOL;
        }

        return $ret;
    }

    private function getFields()
    {
        $ret = '';
        foreach ($this->managers as $manager) {
            $ret .= $this->spaces . 'private $' . lcfirst($manager) . ';' . PHP_EOL;
        }

        return $ret;
    }

    private function getArguments()
    {
        $ret = [];
        foreach ($this->managers as $manager) {
            $ret[] = $this->spaces . $this->spaces . $manager . ' $' . lcfirst($manager);
        }

        return implode(',' . PHP_EOL, $ret);
    }

    private function getAssignment()
    {
        $ret = [];
        foreach ($this->managers as $manager) {
            $ret[] = $this->spaces . $this->spaces . '$this->' . lcfirst($manager) . ' = $'
                . lcfirst($manager) . ';';
        }

        return implode(PHP_EOL, $ret);
    }

    private function getGetters()
    {
        $ret = '';
        foreach ($this->managers as $manager) {
            $ret .= str_replace(['<manager>', '<lcfmanager>'], [$manager, lcfirst($manager)], $this->getterTemplate);
        }

        return $ret;
    }

    private function getFactory()
    {
        return str_replace(
            ['<setters>', '<type>'],
            [$this->getSetters(), ucfirst($this->type)],
            $this->factoryMethodTemplate
        );
    }


    private function getSetters()
    {
        $ret = [];
        foreach ($this->managers as $manager) {
            $ret[] = $this->spaces . $this->spaces . $this->spaces . $this->spaces . '$this->' . lcfirst($manager) .
                '->setObjectManager($em)';
        }

        return implode(',' . PHP_EOL, $ret);
    }
}