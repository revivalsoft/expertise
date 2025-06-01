<?php
// src/Controller/LowessController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LowessController extends AbstractController
{
    #[Route('/lowess', name: 'app_lowess_info')]
    public function index(): Response
    {
        return $this->render('lowess/index.html.twig');
    }
}