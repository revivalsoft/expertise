<?php
// src/Controller/ComparaisonModelesController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ComparaisonModelesController extends AbstractController
{
    #[Route('/comparaison-modeles', name: 'app_comparaison_modeles')]
    public function index(): Response
    {
        return $this->render('comparaison_modeles/index.html.twig');
    }
}
