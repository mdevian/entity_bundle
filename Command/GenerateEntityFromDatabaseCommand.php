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
use Wikimart\EntityBundle\Tool\RepositoryGenerator;

class GenerateEntityFromDatabaseCommand extends DoctrineCommand
{
    private $entityGenerator;
    private $interfaceGenerator;
    private $managerGenerator;
    private $repoGenerator;

    public function configure()
    {
        $this
            ->setName('wikimart:entity:generate')
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

        $type = strpos($input->getOption('em'), 'client') !== false ? 'client' : 'default';

        $servicesFilename = $bundle->getPath() . '/Resources/config/managers/' . $type . '.yml';
        $services         = file_exists($servicesFilename) ? Yaml::parse(file_get_contents($servicesFilename)) : ['services' => []];

        $output->writeln(sprintf('Generating entities for "<info>%s</info>"', $bundle->getName()));
        if ($metadata) {
            foreach ($metadata as $class) {
                $this->deleteDateFromMetadata($class);

                $className = $class->name;

                $entityPath    = $bundle->getPath() . '/Entity/Base/' . $className . '.php';
                $interfacePath = $bundle->getPath() . '/Model/Base/' . $className . 'Interface.php';
                $managerPath   = $bundle->getPath() . '/Model/Manager/' . $className . 'Manager.php';
                $repoPath      = $bundle->getPath() . '/Repository/' . $className . 'Repository.php';

                $class->name                      = $bundle->getNamespace() . '\\Entity\\Base\\' . $className;
                $class->customRepositoryClassName = $bundle->getNamespace(
                    ) . '\\Repository\\' . $className . 'Repository';

                $code = $this->getEntityGenerator()->generateEntityClass($class);

                $this->createFileWithCode($entityPath, $code, $output);

                $class->name = $bundle->getNamespace() . '\\Model\\Base\\' . $className . 'Interface';
                $code        = $this->getInterfaceGenerator()->generateInterfaceClass($class);

                $this->createFileWithCode($interfacePath, $code, $output);

                if (!file_exists($managerPath)) {
                    $class->name = $bundle->getNamespace() . '\\Model\\Manager\\' . $className;
                    $code        = $this->getManagerGenerator()->generateManagerClass($class);

                    $this->createFileWithCode($managerPath, $code, $output);
                }

                if (!file_exists($repoPath)) {
                    $class->name = $bundle->getNamespace() . '\\Repository\\' . $className;
                    $code        = $this->getRepoGenerator()->generateRepoClass($class);

                    $this->createFileWithCode($repoPath, $code, $output);
                }

                if (!isset($services['services']['manager.' . $type . '.' . strtolower($className)])) {
                    $services['services']['manager.' . $type . '.' . strtolower($className)] = [
                        'class'     => $bundle->getNamespace() . '\\Model\\Manager\\' . $className . 'Manager',
                        'arguments' => [
                            $type == 'default' ? '@doctrine.orm.default_entity_manager' : '@=service(\'manager_configurator\').getManager()',
                            $bundle->getNamespace() . '\\Entity\\Base\\' . $className
                        ]
                    ];
                }

                $output->writeln('');
            }
        } else {
            $output->writeln('Database does not have any mapping information.', 'ERROR');
            $output->writeln('', 'ERROR');
        }

        $this->createFileWithCode(
            $bundle->getPath() . '/Resources/config/managers/' . $type . '.yml',
            str_replace(
                '""',
                "'",
                str_replace("'", '"', Yaml::dump($services, 3))
            ),
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
     * @return RepositoryGenerator
     */
    protected function getRepoGenerator()
    {
        if (!$this->repoGenerator) {
            $this->repoGenerator = new RepositoryGenerator();
            $this->repoGenerator->setRegenerateEntityIfExists(false);
            $this->repoGenerator->setUpdateEntityIfExists(true);
            $this->repoGenerator->setNumSpaces(4);
            $this->repoGenerator->setGenerateStubMethods(true);
        }

        return $this->repoGenerator;
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
