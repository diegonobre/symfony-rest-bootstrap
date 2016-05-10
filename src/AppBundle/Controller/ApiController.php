<?php

// src/AppBundle/Controller/ApiController.php

namespace AppBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Get;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class ApiController extends FOSRestController
{
    /**
     * This is the 'Hello World' method. This method require authentication.
     * If you want to test an authenticated call, use the following:
     * 
     * http POST http://localhost:8000/app_dev.php/oauth/v2/token \
     *   grant_type=password \
     *   client_id=1_3bcbxd9e24g0gk4swg0kwgcwg4o8k8g4g888kwc44gcc0gwwk4 \
     *   client_secret=4ok2x70rlfokc8g0wws8c8kwcokw80k44sg48goc0ok4w0so0k \
     *   username=admin \
     *   password=admin
     * 
     * @ApiDoc(
     *   resource=true,
     *   description="Returns JSON formatted 'Hello world!'"
     * )
     * 
     * @Get("/hello-world")
     */
    public function helloWorldAction()
    {
        $data = array("hello" => "world");
        $view = $this->view($data);
        return $this->handleView($view);
    }
}
