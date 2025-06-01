<?php
// src/Controller/LogarithmiqueInfoController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LogarithmiqueInfoController extends AbstractController
{
    #[Route('/logarithmique-info', name: 'app_regression_logarithmique_info')]
    public function index(): Response
    {
        return $this->render('logarithmique_info/index.html.twig');
    }
}
