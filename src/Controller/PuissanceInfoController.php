<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PuissanceInfoController extends AbstractController
{
    #[Route('/regression-puissance', name: 'app_regression_puissance_info')]
    public function index(): Response
    {
        return $this->render('regression_puissance_info/index.html.twig');
    }
}
