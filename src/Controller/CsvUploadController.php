<?php
/*
 * Estimations - Logiciel d'aide à l'estimation de terrains
 * Copyright (C) 2025 RevivalSoft
 *
 * Ce programme est un logiciel libre ; vous pouvez le redistribuer et/ou
 * le modifier selon les termes de la Licence Publique Générale GNU publiée
 * par la Free Software Foundation Version 3.
 *
 * Ce programme est distribué dans l'espoir qu'il sera utile,
 * mais SANS AUCUNE GARANTIE ; sans même la garantie implicite de
 * COMMERCIALISATION ou D’ADÉQUATION À UN BUT PARTICULIER. Voir la
 * Licence Publique Générale GNU pour plus de détails.
 *
 * Vous devriez avoir reçu une copie de la Licence Publique Générale GNU
 * avec ce programme ; si ce n'est pas le cas, voir
 * <https://www.gnu.org/licenses/>.
 */

namespace App\Controller;

use App\Form\CsvUploadType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CsvUploadController extends AbstractController
{
    #[Route('/upload', name: 'csv_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {


        $form = $this->createForm(CsvUploadType::class);
        $form->handleRequest($request);

        $message = null;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $csvFile */
            $csvFile = $form->get('csv')->getData();



            if ($csvFile) {
                $originalFilename = pathinfo($csvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $csvFile->guessExtension() ?: 'csv';
                $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                $newFilename = $safeFilename . '.' . $extension;

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';

                // Empêche le doublon
                if (file_exists($uploadDir . '/' . $newFilename)) {
                    $message = '❌ Le fichier existe déjà. Veuillez renommer votre fichier ou en choisir un autre.';
                } else {
                    try {
                        $csvFile->move($uploadDir, $newFilename);
                        $message = '✅ Fichier uploadé avec succès : ' . $newFilename;
                    } catch (FileException $e) {
                        $message = '❌ Une erreur est survenue lors du téléchargement.';
                    }
                }
            }
        }

        // Récupère la liste des fichiers existants
        $csvFiles = glob($this->getParameter('kernel.project_dir') . '/public/uploads/*.csv');
        $csvFiles = array_map('basename', $csvFiles);

        return $this->render('csv_upload/index.html.twig', [
            'form' => $form->createView(),
            'csvFiles' => $csvFiles,
            'message' => $message,

        ]);
    }


    #[Route('/csv/delete/{filename}', name: 'csv_delete', methods: ['POST'])]
    public function deleteCsv(string $filename, Request $request): Response
    {
        // $csrfToken = $request->request->get('_token');
        // if (!$this->isCsrfTokenValid('delete_csv_' . $filename, $csrfToken)) {
        //     throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        // }

        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/';
        $filePath = $uploadsDir . '/' . basename($filename);

        if (file_exists($filePath)) {
            unlink($filePath);
            $this->addFlash('success', 'Fichier supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Fichier introuvable.');
        }

        return $this->redirectToRoute('csv_upload');
    }
}
