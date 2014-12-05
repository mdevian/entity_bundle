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


class InterfaceGenerator extends BaseEntityGenerator
{
    /**
     * The actual spaces to use for indention.
     *
     * @var string
     */
    protected $spaces = '    ';

    /**
     * {@inheritdoc}
     */
    protected static $classTemplate =
        '<?php

<namespace>

<interfaceAnnotation>
<interfaceClassName>
{
<interfaceBody>
}
';

    /**
     * @var string
     */
    protected static $getMethodTemplate =
        '/**
 * <description>
 *
 * @return <variableType>
 */
public function <methodName>();';

    /**
     * @var string
     */
    protected static $setMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 * @return <entity>
 */
public function <methodName>(<methodTypeHint>$<variableName><variableDefault>);';

    /**
     * @var string
     */
    protected static $addMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 * @return <entity>
 */
public function <methodName>(<methodTypeHint>$<variableName>);';

    /**
     * @var string
     */
    protected static $removeMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 */
public function <methodName>(<methodTypeHint>$<variableName>);';

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    public function generateInterfaceClass(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<interfaceAnnotation>',
            '<interfaceClassName>',
            '<interfaceBody>'
        );

        $replacements = array(
            $this->generateInterfaceNamespace($metadata),
            $this->generateInterfaceDocBlock($metadata),
            $this->generateInterfaceClassName($metadata),
            $this->generateInterfaceBody($metadata)
        );

        $code = str_replace($placeHolders, $replacements, self::$classTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateInterfaceNamespace(ClassMetadataInfo $metadata)
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
    protected function generateInterfaceClassName(ClassMetadataInfo $metadata)
    {
        return 'interface ' . $this->getClassName($metadata);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateInterfaceDocBlock(ClassMetadataInfo $metadata)
    {
        $lines   = array();
        $lines[] = '/**';
        $lines[] = ' * ' . $this->getClassName($metadata);
        $lines[] = ' */';

        return implode("\n", $lines);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateInterfaceBody(ClassMetadataInfo $metadata)
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
                    $associationMapping['targetEntity'] . '[]'
                )
                ) {
                    $methods[] = $code;
                }
            }
        }

        return implode("\n\n", $methods);
    }

    /**
     * @param ClassMetadataInfo $metadata
     * @param string            $type
     * @param string            $fieldName
     * @param string|null       $typeHint
     * @param string|null       $defaultValue
     *
     * @return string
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