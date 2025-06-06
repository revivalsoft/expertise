<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CleanerInfoController extends AbstractController
{
    #[Route('/cleaner/info', name: 'app_cleaner_info')]
    public function index(): Response
    {
        return $this->render('cleaner_info/index.html.twig', [
            'controller_name' => 'CleanerInfoController',
        ]);
    }
}
