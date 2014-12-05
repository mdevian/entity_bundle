<?php
namespace Wikimart\EntityBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DoctrineCommand;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Yaml\Yaml;
use Wikimart\EntityBundle\Tool\EntityGenerator;
use Wikimart\EntityBundle\Tool\InterfaceGenerator;
use Wikimart\EntityBundle\Tool\ManagerGenerator;

class GenerateEntityFromDatabaseCommand extends DoctrineCommand
{
    private $entityGenerator;
    private $interfaceGenerator;
    private $managerGenerator;

    public function configure()
    {
        $this
            ->setName('stool:entity:generate')
            ->setDescription('Generate entities into from database')
            ->addArgument('bundle', null, InputArgument::REQUIRED, 'The destination bundle for entities.')
            ->addOption(
                'em',
                null,
                InputOption::VALUE_REQUIRED,
                'The entity manager to use for this command.',
                'default'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var BundleInterface $bundle
         */
        $bundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('bundle'));

        $this->runCommand(
            $output,
            [
                'command'         => 'doctrine:mapping:convert',
                'to-type'         => 'yml',
                'dest-path'       => './src/' . $bundle->getName() . '/Resources/config/doctrine/metadata/orm',
                '--from-database' => true,
                '--force'         => true,
                '--em'            => $input->getOption('em')
            ]
        );

        $em  = $this->getContainer()->get('doctrine')->getManager($input->getOption('em'));
        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager($em);
        $metadata = $cmf->getAllMetadata();


        $services = [];
        $output->writeln(sprintf('Generating entities for "<info>%s</info>"', $bundle->getName()));
        if ($metadata) {
            foreach ($metadata as $class) {
                $this->deleteDateFromMetadata($class);

                $className = $class->name;

                $entityPath    = $bundle->getPath() . '/Entity/' . $className . '.php';
                $interfacePath = $bundle->getPath() . '/Model/Base/' . $className . 'Interface.php';
                $managerPath   = $bundle->getPath() . '/Model/Manager/' . $className . 'Manager.php';
                $class->name   = $bundle->getNamespace() . '\\Entity\\' . $className;
                $code          = $this->getEntityGenerator()->generateEntityClass($class);

                $this->createFileWithCode($entityPath, $code, $output);

                $class->name = $bundle->getNamespace() . '\\Model\\Base\\' . $className . 'Interface';
                $code        = $this->getInterfaceGenerator()->generateInterfaceClass($class);

                $this->createFileWithCode($interfacePath, $code, $output);

                $class->name = $bundle->getNamespace() . '\\Model\\Manager\\' . $className;
                $code        = $this->getManagerGenerator()->generateManagerClass($class);

                $this->createFileWithCode($managerPath, $code, $output);

                $services['manager.' . $input->getOption('em') . '.' . strtolower($className)] = [
                    'class'     => $bundle->getNamespace() . '\\Model\\Manager\\' . $className . 'Manager',
                    'arguments' => [
                        '\@doctrine.orm.' . $input->getOption('em') . '_entity_manager',
                        $bundle->getNamespace() . '\\Entity\\' . $className
                    ]
                ];
                $output->writeln('');
            }
        } else {
            $output->writeln('Database does not have any mapping information.', 'ERROR');
            $output->writeln('', 'ERROR');
        }

        $this->createFileWithCode(
            $bundle->getPath() . '/Resources/config/managers/' . $input->getOption('em') . '.yml',
            str_replace('\@', '@', Yaml::dump(['services' => $services], 3)),
            $output
        );
    }

    /**
     * Run console command
     *
     * @param OutputInterface $output
     * @param array           $arrayInput
     *
     * @throws \Exception
     */
    private function runCommand(OutputInterface $output, array $arrayInput)
    {
        $command = $this->getApplication()->find($arrayInput['command']);
        $input   = new ArrayInput($arrayInput);
        $output->writeln('Start ' . $arrayInput['command']);
        $command->run($input, $output);
        $output->writeln('End ' . $arrayInput['command']);
        $output->writeln('');
    }

    private function deleteDateFromMetadata(ClassMetadataInfo $metadata)
    {
        $this->deleteColumnFromMetadata($metadata, 'created_at');
        $this->deleteColumnFromMetadata($metadata, 'updated_at');
    }

    private function deleteColumnFromMetadata(ClassMetadataInfo $metadata, $field)
    {
        $camelizedField = Inflector::camelize($field);
        unset($metadata->fieldNames[$field]);
        unset($metadata->fieldMappings[$camelizedField]);
        unset($metadata->columnNames[$camelizedField]);
        unset($metadata->reflFields[$camelizedField]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEntityGenerator()
    {
        if (!$this->entityGenerator) {
            $this->entityGenerator = new EntityGenerator();
            $this->entityGenerator->setGenerateAnnotations(true);
            $this->entityGenerator->setGenerateStubMethods(true);
            $this->entityGenerator->setRegenerateEntityIfExists(true);
            $this->entityGenerator->setUpdateEntityIfExists(true);
            $this->entityGenerator->setNumSpaces(4);
            $this->entityGenerator->setAnnotationPrefix('ORM\\');
        }

        return $this->entityGenerator;
    }

    /**
     * @return InterfaceGenerator
     */
    protected function getInterfaceGenerator()
    {
        if (!$this->interfaceGenerator) {
            $this->interfaceGenerator = new InterfaceGenerator();
            $this->interfaceGenerator->setRegenerateEntityIfExists(true);
            $this->interfaceGenerator->setUpdateEntityIfExists(true);
            $this->interfaceGenerator->setNumSpaces(4);
            $this->interfaceGenerator->setGenerateStubMethods(true);
        }

        return $this->interfaceGenerator;
    }

    /**
     * @return ManagerGenerator
     */
    protected function getManagerGenerator()
    {
        if (!$this->managerGenerator) {
            $this->managerGenerator = new ManagerGenerator();
            $this->managerGenerator->setRegenerateEntityIfExists(true);
            $this->managerGenerator->setUpdateEntityIfExists(true);
            $this->managerGenerator->setNumSpaces(4);
            $this->managerGenerator->setGenerateStubMethods(true);
        }

        return $this->managerGenerator;
    }

    /**
     * @param string          $path
     * @param string          $code
     * @param OutputInterface $output
     */
    private function createFileWithCode($path, $code, OutputInterface $output = null)
    {
        if (!is_dir($dir = dirname($path))) {
            mkdir($dir, 0777, true);
        }
        if ($output) {
            $output->writeln(sprintf('  > writing <info>%s</info>', $path));
        }
        file_put_contents($path, $code);
    }
}
