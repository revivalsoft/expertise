<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RegressionLineaireInfoController extends AbstractController
{
    #[Route('/regression-lineaire', name: 'app_regression_lineaire_info')]
    public function index(): Response
    {
        return $this->render('regression_lineaire_info/index.html.twig');
    }
}
