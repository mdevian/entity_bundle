<?php

namespace Wikimart\EntityBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('WikimartEntityBundle:Default:index.html.twig', array('name' => $name));
    }
}
