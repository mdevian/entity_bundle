<?php
/**
 * Class InterfaceGenerator
 *
 * @package Wikimart\EntityBundle\Tool
 * @author  devian
 */


namespace Wikimart\EntityBundle\Tool;

use Doctrine\ORM\Tools\EntityGenerator as BaseEntityGenerator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Common\Util\Inflector;
use Doctrine\DBAL\Types\Type;

class EntityGenerator extends BaseEntityGenerator
{
    /**
     * {@inheritdoc}
     */
    protected static $classTemplate =
        '<?php

<namespace>

use Doctrine\ORM\Mapping as ORM;
<entityUse>

<entityAnnotation>
<entityClassName>
{
<entityBody>
}
';

    protected $classesToUse = [];

    /**
     * {@inheritdoc}
     */
    public function generateEntityClass(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<entityAnnotation>',
            '<entityClassName>',
            '<entityBody>'
        );

        $replacements = array(
            $this->generateEntityNamespace($metadata),
            $this->generateEntityDocBlock($metadata),
            $this->generateEntityClassName($metadata),
            $this->generateEntityBody($metadata)
        );

        $code = str_replace($placeHolders, $replacements, self::$classTemplate);
        $code = str_replace('<entityUse>', $this->generateEntityUse($metadata), $code);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * {@inheritdoc}
     */
    protected function generateEntityClassName(ClassMetadataInfo $metadata)
    {
        return 'class ' . $this->getClassName($metadata) .
        ($this->extendsClass() ? ' extends ' . $this->getClassToExtendName() : null) .
        ' implements ' . $this->getClassName($metadata) . 'Interface';
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityUse(ClassMetadataInfo $metadata)
    {
        $ret = '';

        $this->classesToUse[$this->getClassName($metadata)][] = $this->getClassName($metadata) . 'Interface';
        $this->classesToUse[$this->getClassName($metadata)]   = array_unique(
            $this->classesToUse[$this->getClassName($metadata)]
        );
        foreach ($this->classesToUse[$this->getClassName($metadata)] as $class) {
            $ret .= 'use ' . str_replace('\\Entity\\', '\\Model\\', $this->getNamespace($metadata)) .
                '\\' . $class . ';' . "\n";
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateAssociationMappingPropertyDocBlock(
        array $associationMapping,
        ClassMetadataInfo $metadata
    ) {
        $lines   = array();
        $lines[] = $this->spaces . '/**';


        if ($associationMapping['type'] & ClassMetadataInfo::TO_MANY) {
            $lines[] = $this->spaces . ' * @var \Doctrine\Common\Collections\Collection';
        } else {
            if (strpos($associationMapping['targetEntity'], '\\') === false) {
                $lines[] = $this->spaces . ' * @var ' . $associationMapping['targetEntity'] . 'Interface';
            } else {
                $lines[] = $this->spaces . ' * @var \\' . ltrim(
                        $associationMapping['targetEntity'],
                        '\\'
                    ) . 'Interface';
            }
        }

        if ($this->generateAnnotations) {
            $lines[] = $this->spaces . ' *';

            if (isset($associationMapping['id']) && $associationMapping['id']) {
                $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'Id';

                if ($generatorType = $this->getIdGeneratorTypeString($metadata->generatorType)) {
                    $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'GeneratedValue(strategy="' . $generatorType . '")';
                }
            }

            $type = null;
            switch ($associationMapping['type']) {
                case ClassMetadataInfo::ONE_TO_ONE:
                    $type = 'OneToOne';
                    break;
                case ClassMetadataInfo::MANY_TO_ONE:
                    $type = 'ManyToOne';
                    break;
                case ClassMetadataInfo::ONE_TO_MANY:
                    $type = 'OneToMany';
                    break;
                case ClassMetadataInfo::MANY_TO_MANY:
                    $type = 'ManyToMany';
                    break;
            }
            $typeOptions = array();

            if (isset($associationMapping['targetEntity'])) {
                $typeOptions[] = 'targetEntity="' . $associationMapping['targetEntity'] . '"';
            }

            if (isset($associationMapping['inversedBy'])) {
                $typeOptions[] = 'inversedBy="' . $associationMapping['inversedBy'] . '"';
            }

            if (isset($associationMapping['mappedBy'])) {
                $typeOptions[] = 'mappedBy="' . $associationMapping['mappedBy'] . '"';
            }

            if ($associationMapping['cascade']) {
                $cascades = array();

                if ($associationMapping['isCascadePersist']) {
                    $cascades[] = '"persist"';
                }
                if ($associationMapping['isCascadeRemove']) {
                    $cascades[] = '"remove"';
                }
                if ($associationMapping['isCascadeDetach']) {
                    $cascades[] = '"detach"';
                }
                if ($associationMapping['isCascadeMerge']) {
                    $cascades[] = '"merge"';
                }
                if ($associationMapping['isCascadeRefresh']) {
                    $cascades[] = '"refresh"';
                }

                if (count($cascades) === 5) {
                    $cascades = array('"all"');
                }

                $typeOptions[] = 'cascade={' . implode(',', $cascades) . '}';
            }

            if (isset($associationMapping['orphanRemoval']) && $associationMapping['orphanRemoval']) {
                $typeOptions[] = 'orphanRemoval=' . ($associationMapping['orphanRemoval'] ? 'true' : 'false');
            }

            if (isset($associationMapping['fetch']) && $associationMapping['fetch'] !== ClassMetadataInfo::FETCH_LAZY) {
                $fetchMap = array(
                    ClassMetadataInfo::FETCH_EXTRA_LAZY => 'EXTRA_LAZY',
                    ClassMetadataInfo::FETCH_EAGER      => 'EAGER',
                );

                $typeOptions[] = 'fetch="' . $fetchMap[$associationMapping['fetch']] . '"';
            }

            $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . '' . $type . '(' . implode(
                    ', ',
                    $typeOptions
                ) . ')';

            if (isset($associationMapping['joinColumns']) && $associationMapping['joinColumns']) {
                $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'JoinColumns({';

                $joinColumnsLines = array();

                foreach ($associationMapping['joinColumns'] as $joinColumn) {
                    if ($joinColumnAnnot = $this->generateJoinColumnAnnotation($joinColumn)) {
                        $joinColumnsLines[] = $this->spaces . ' *   ' . $joinColumnAnnot;
                    }
                }

                $lines[] = implode(",\n", $joinColumnsLines);
                $lines[] = $this->spaces . ' * })';
            }

            if (isset($associationMapping['joinTable']) && $associationMapping['joinTable']) {
                $joinTable   = array();
                $joinTable[] = 'name="' . $associationMapping['joinTable']['name'] . '"';

                if (isset($associationMapping['joinTable']['schema'])) {
                    $joinTable[] = 'schema="' . $associationMapping['joinTable']['schema'] . '"';
                }

                $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'JoinTable(' . implode(
                        ', ',
                        $joinTable
                    ) . ',';
                $lines[] = $this->spaces . ' *   joinColumns={';

                $joinColumnsLines = array();

                foreach ($associationMapping['joinTable']['joinColumns'] as $joinColumn) {
                    $joinColumnsLines[] = $this->spaces . ' *     ' . $this->generateJoinColumnAnnotation($joinColumn);
                }

                $lines[] = implode("," . PHP_EOL, $joinColumnsLines);
                $lines[] = $this->spaces . ' *   },';
                $lines[] = $this->spaces . ' *   inverseJoinColumns={';

                $inverseJoinColumnsLines = array();

                foreach ($associationMapping['joinTable']['inverseJoinColumns'] as $joinColumn) {
                    $inverseJoinColumnsLines[] = $this->spaces . ' *     ' . $this->generateJoinColumnAnnotation(
                            $joinColumn
                        );
                }

                $lines[] = implode("," . PHP_EOL, $inverseJoinColumnsLines);
                $lines[] = $this->spaces . ' *   }';
                $lines[] = $this->spaces . ' * )';
            }

            if (isset($associationMapping['orderBy'])) {
                $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'OrderBy({';

                foreach ($associationMapping['orderBy'] as $name => $direction) {
                    $lines[] = $this->spaces . ' *     "' . $name . '"="' . $direction . '",';
                }

                $lines[count($lines) - 1] = substr($lines[count($lines) - 1], 0, strlen($lines[count($lines) - 1]) - 1);
                $lines[]                  = $this->spaces . ' * })';
            }
        }

        $lines[] = $this->spaces . ' */';

        return implode("\n", $lines);
    }

    /**
     * {@inheritdoc}
     */
    protected function generateEntityStubMethod(
        ClassMetadataInfo $metadata,
        $type,
        $fieldName,
        $typeHint = null,
        $defaultValue = null
    ) {
        $methodName = $type . Inflector::classify($fieldName);
        if (in_array($type, array("add", "remove"))) {
            $methodName = Inflector::singularize($methodName);
        }

        if ($this->hasMethod($methodName, $metadata)) {
            return '';
        }
        $this->staticReflection[$metadata->name]['methods'][] = $methodName;

        $var      = sprintf('%sMethodTemplate', $type);
        $template = self::$$var;

        $methodTypeHint = null;
        $types          = Type::getTypesMap();
        $variableType   = $typeHint ? $this->getType($typeHint) . ' ' : null;

        if ($typeHint && !isset($types[$typeHint])) {
            $methodTypeHint = $typeHint . ' ';
        }

        $replacements = array(
            '<description>'     => ucfirst($type) . ' ' . $fieldName,
            '<methodTypeHint>'  => $methodTypeHint,
            '<variableType>'    => $variableType,
            '<variableName>'    => Inflector::camelize($fieldName),
            '<methodName>'      => $methodName,
            '<fieldName>'       => $fieldName,
            '<variableDefault>' => ($defaultValue !== null) ? (' = ' . $defaultValue) : '',
            '<entity>'          => $this->getClassName($metadata)
        );

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $this->prefixCodeWithSpaces($method);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityStubMethods(ClassMetadataInfo $metadata)
    {
        $methods = array();

        foreach ($metadata->fieldMappings as $fieldMapping) {
            if (!isset($fieldMapping['id']) || !$fieldMapping['id'] || $metadata->generatorType == ClassMetadataInfo::GENERATOR_TYPE_NONE) {
                if ($code = $this->generateEntityStubMethod(
                    $metadata,
                    'set',
                    $fieldMapping['fieldName'],
                    $fieldMapping['type']
                )
                ) {
                    $methods[] = $code;
                }
            }

            if ($code = $this->generateEntityStubMethod(
                $metadata,
                'get',
                $fieldMapping['fieldName'],
                $fieldMapping['type']
            )
            ) {
                $methods[] = $code;
            }
        }

        foreach ($metadata->associationMappings as $associationMapping) {
            $associationMapping['targetEntity'] .= 'Interface';
            $this->classesToUse[$this->getClassName($metadata)][] = $associationMapping['targetEntity'];
            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                $nullable = $this->isAssociationIsNullable($associationMapping) ? 'null' : null;
                if ($code = $this->generateEntityStubMethod(
                    $metadata,
                    'set',
                    $associationMapping['fieldName'],
                    $associationMapping['targetEntity'],
                    $nullable
                )
                ) {
                    $methods[] = $code;
                }
                if ($code = $this->generateEntityStubMethod(
                    $metadata,
                    'get',
                    $associationMapping['fieldName'],
                    $associationMapping['targetEntity']
                )
                ) {
                    $methods[] = $code;
                }
            } elseif ($associationMapping['type'] & ClassMetadataInfo::TO_MANY) {
                if ($code = $this->generateEntityStubMethod(
                    $metadata,
                    'add',
                    $associationMapping['fieldName'],
                    $associationMapping['targetEntity']
                )
                ) {
                    $methods[] = $code;
                }
                if ($code = $this->generateEntityStubMethod(
                    $metadata,
                    'remove',
                    $associationMapping['fieldName'],
                    $associationMapping['targetEntity']
                )
                ) {
                    $methods[] = $code;
                }
                if ($code = $this->generateEntityStubMethod(
                    $metadata,
                    'get',
                    $associationMapping['fieldName'],
                    'Doctrine\Common\Collections\Collection'
                )
                ) {
                    $methods[] = $code;
                }
            }
        }

        return implode("\n\n", $methods);
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getType($type)
    {
        if (isset($this->typeAlias[$type])) {
            return $this->typeAlias[$type];
        }

        $types = Type::getTypesMap();
        if (!in_array($type, ['integer', 'string', 'array', 'boolean', 'float']) && isset($types[$type])) {
            return 'mixed';
        }

        return $type;
    }
} 