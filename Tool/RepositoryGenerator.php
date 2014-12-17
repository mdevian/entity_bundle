<?php
/**
 * Class InterfaceGenerator
 *
 * @package Wikimart\EntityBundle\Tool
 * @author  devian
 */


namespace Wikimart\EntityBundle\Tool;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\EntityGenerator as BaseEntityGenerator;

class RepositoryGenerator extends BaseEntityGenerator
{

    /**
     * {@inheritdoc}
     */
    protected static $classTemplate =
        '<?php

<namespace>

use Doctrine\ORM\EntityRepository;

<repoAnnotation>
<repoClassName>
{
}
';

    /**
     * @var string
     */
    protected static $annotationTemplate =
        '/**
 * Class <classname>Repository
 *
 * @package <namespace>
 *
 */';

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    public function generateRepoClass(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<repoAnnotation>',
            '<repoClassName>',
        );

        $replacements = array(
            $this->generateRepoNamespace($metadata),
            $this->generateRepoDocBlock($metadata),
            $this->generateRepoClassName($metadata),
        );

        $code = str_replace($placeHolders, $replacements, self::$classTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateRepoNamespace(ClassMetadataInfo $metadata)
    {
        if ($this->hasNamespace($metadata)) {
            return 'namespace ' . $this->getNamespace($metadata) .';';
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
    protected function generateRepoClassName(ClassMetadataInfo $metadata)
    {
        return 'class ' . $this->getClassName($metadata) . ' extends EntityRepository';
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateRepoDocBlock(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<classname>',
        );

        $replacements = array(
            $this->getNamespace($metadata),
            $this->getClassName($metadata),
        );

        return str_replace($placeHolders, $replacements, self::$annotationTemplate);
    }

} 