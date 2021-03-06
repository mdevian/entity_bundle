<?php
/**
 * Model Manager
 *
 * @package Wikimart\EntityBundle\Model
 * @author  devian
 */


namespace Wikimart\EntityBundle\Model;

use Doctrine\Common\Persistence\ObjectManager;

abstract class Manager
{
    private $objectManager;
    private $repository;
    private $class;

    /**
     * @param ObjectManager $om
     * @param string        $class
     */
    public function __construct(ObjectManager $om, $class)
    {
        $this->validateClass($class);

        $this->objectManager = $om;
        $this->repository    = $om->getRepository($class);

        $metadata    = $om->getClassMetadata($class);
        $this->class = $metadata->getName();
    }

    /**
     * @param ObjectManager $om
     *
     * @return $this
     */
    public function setObjectManager(ObjectManager $om)
    {
        $this->objectManager = $om;
        $this->repository    = $om->getRepository($this->getClass());

        return $this;
    }

    /**
     * @return string
     */
    abstract public function getInterface();

    /**
     * @return string
     */
    public function getClass()
    {
        return (string) $this->class;
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param $object
     */
    private function validateObject($object)
    {
        $class = $this->getClass();
        if (!($object instanceof $class)) {
            throw new InvalidArgumentException(
                'Object "' . get_class($object) . '" must to extend the class: "' . $class . '"'
            );
        }
    }

    /**
     * @param string $class
     */
    private function validateClass($class)
    {
        $object    = new $class;
        $interface = (string) $this->getInterface();

        if (!($object instanceof $interface)) {
            throw new InvalidArgumentException(
                'Class "' . $class . '" must to implement the interface: "' . $interface . '"'
            );
        }
    }

    /**
     * @return mixed
     */
    public function create()
    {
        $class = $this->getClass();
        $user  = new $class;

        return $user;
    }


    /**
     * @param $object
     */
    public function delete($object)
    {
        $this->validateObject($object);
        $this->objectManager->remove($object);
        $this->objectManager->flush();
    }

    /**
     * @param      $object
     * @param bool $andFlush
     */
    public function update($object, $andFlush = true)
    {
        $this->validateObject($object);
        $this->objectManager->persist($object);
        if ($andFlush) {
            $this->flush();
        }
    }

    /**
     * @param $object
     */
    public function reload($object)
    {
        $this->validateObject($object);
        $this->objectManager->refresh($object);
    }

    public function flush()
    {
        $this->objectManager->flush();
    }

}