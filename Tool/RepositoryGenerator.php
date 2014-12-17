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
use <modelNamespace>\<classname>Interface;
use Doctrine\DBAL\LockMode;

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

 * @method <classname>Interface|null find($id, $lockMode = LockMode::NONE, $lockVersion = null)
 * @method <classname>Interface[]    findAll()
 * @method <classname>Interface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method <classname>Interface|null findOneBy(array $criteria, array $orderBy = null)
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
            '<modelNamespace>',
            '<classname>'
        );

        $replacements = array(
            $this->generateRepoNamespace($metadata),
            $this->generateRepoDocBlock($metadata),
            $this->generateRepoClassName($metadata),
            str_replace('\\Repository', '\\Model\\Base', $this->getNamespace($metadata)),
            $this->getClassName($metadata),
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
        return 'class ' . $this->getClassName($metadata) . 'Repository extends EntityRepository';
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