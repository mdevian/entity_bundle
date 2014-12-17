<?php
/**
 * Class InterfaceGenerator
 *
 * @package Wikimart\EntityBundle\Tool
 * @author  devian
 */


namespace Wikimart\EntityBundle\Tool;

use Doctrine\Common\Util\Inflector;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\EntityGenerator as BaseEntityGenerator;
use Doctrine\DBAL\Types\Type;


class ManagerGenerator extends BaseEntityGenerator
{

    /**
     * {@inheritdoc}
     */
    protected static $classTemplate =
        '<?php

<namespace>

use Wikimart\EntityBundle\Model\Manager;
use <modelNamespace>\<classname>Interface;
use <repoNamespace>\<classname>Repository;

<managerAnnotation>
<managerClassName>
{
<managerBody>
}
';

    /**
     * @var string
     */
    protected static $annotationTemplate =
        '/**
 * Class <classname>Manager
 *
 * @package <namespace>
 *
 * @method <classname>Interface  create()
 * @method <emptyStub> update(<classname>Interface $object, $andFlush = true)
 * @method <emptyStub> delete(<classname>Interface $object)
 * @method <emptyStub> reload(<classname>Interface $object)
 * @method <classname>Repository getRepository()
 */';

    /**
     * @var string
     */
    protected static $bodyTemplate =
        '    /**
     * @return string
     */
    public function getInterface()
    {
        return "\\\\<modelNamespace>\\\\<classname>Interface";
    }
';


    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    public function generateManagerClass(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<classname>',
            '<managerAnnotation>',
            '<managerClassName>',
            '<managerBody>',
            '<modelNamespace>',
            '<repoNamespace>',
        );

        $replacements = array(
            $this->generateManagerNamespace($metadata),
            $this->getClassName($metadata),
            $this->generateManagerDocBlock($metadata),
            $this->generateManagerClassName($metadata),
            $this->generateManagerBody($metadata),
            str_replace('\\Model\\Manager', '\\Model\\Base', $this->getNamespace($metadata)),
            str_replace('\\Model\\Manager', '\\Repository', $this->getNamespace($metadata))
        );

        $code = str_replace($placeHolders, $replacements, self::$classTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateManagerNamespace(ClassMetadataInfo $metadata)
    {
        if ($this->hasNamespace($metadata)) {
            return 'namespace ' . $this->getNamespace($metadata) . ';';
        } else {
            return '';
        }
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return bool
     */
    protected function hasNamespace(ClassMetadataInfo $metadata)
    {
        return strpos($metadata->name, '\\') ? true : false;
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateManagerClassName(ClassMetadataInfo $metadata)
    {
        return 'class ' . $this->getClassName($metadata) . 'Manager extends Manager';
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateManagerDocBlock(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<classname>',
            '<emptyStub>',
        );

        $replacements = array(
            $this->getNamespace($metadata),
            $this->getClassName($metadata),
            str_repeat(' ', mb_strlen($this->getClassName($metadata)) + 10),
        );

        return str_replace($placeHolders, $replacements, self::$annotationTemplate);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateManagerBody(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<classname>',
            '<modelNamespace>'
        );

        $replacements = array(
            $this->getClassName($metadata),
            str_replace('\\', '\\\\', str_replace('\\Model\\Manager', '\\Model\\Base', $this->getNamespace($metadata))),
        );

        return str_replace($placeHolders, $replacements, self::$bodyTemplate);
    }
} 