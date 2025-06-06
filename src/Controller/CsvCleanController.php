<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CsvCleanController extends AbstractController
{
    #[Route('/nettoyage-modele', name: 'app_nettoyage_modele')]
    public function index(Request $request): Response
    {
        $dataDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $files = glob($dataDir . '/*.csv');

        $fileChoices = [];
        foreach ($files as $path) {
            $filename = basename($path);
            $fileChoices[$filename] = $path;
        }

        $selectedFile = $request->query->get('file');
        $tolerance = floatval($request->query->get('tol', 0));
        $maxViol = (int) $request->query->get('maxViol', 2);
        $action = $request->query->get('action');

        $selectedPath = $selectedFile && isset($fileChoices[$selectedFile]) ? $fileChoices[$selectedFile] : null;

        $nbTotal = 0;
        $nbConserve = 0;
        $outputFile = null;
        $data = [];
        $indexesConserves = [];

        if ($selectedPath && $action === 'nettoyer') {
            if (($handle = fopen($selectedPath, 'r')) !== false) {
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($row) >= 2 && is_numeric($row[0]) && is_numeric($row[1])) {
                        $surface = floatval(str_replace(',', '.', $row[0]));
                        $prix = floatval(str_replace(',', '.', $row[1]));
                        $prixM2 = $surface > 0 ? $prix / $surface : null;
                        $data[] = [
                            'surface' => $surface,
                            'prix' => $prix,
                            'prix_m2' => $prixM2,
                            'violations' => 0,
                        ];
                    }
                }
                fclose($handle);
            }

            $nbTotal = count($data);
            usort($data, fn($a, $b) => $a['surface'] <=> $b['surface']);

            for ($i = 0; $i < $nbTotal; $i++) {
                for ($j = $i + 1; $j < $nbTotal; $j++) {
                    // Nouvelle logique plus intuitive : on attend une baisse d'au moins (tol %) ou une stabilité acceptable
                    if ($data[$j]['prix_m2'] > $data[$i]['prix_m2'] * (1 + $tolerance / 100)) {
                        $data[$i]['violations']++;
                        $data[$j]['violations']++;
                    }
                }
            }

            $indexesConserves = [];
            foreach ($data as $index => $row) {
                if ($row['violations'] <= $maxViol) {
                    $indexesConserves[] = $index;
                }
            }

            $filtered = array_intersect_key($data, array_flip($indexesConserves));

            $outputName = pathinfo($selectedFile, PATHINFO_FILENAME) . '_clean.csv';
            $outputPath = $dataDir . '/' . $outputName;
            $handle = fopen($outputPath, 'w');
            foreach ($filtered as $row) {
                fputcsv($handle, [$row['surface'], $row['prix']]);
            }
            fclose($handle);

            $nbConserve = count($filtered);
            $outputFile = $outputName;
        }

        return $this->render('clean/index.html.twig', [
            'files' => array_keys($fileChoices),
            'selectedFile' => $selectedFile,
            'outputFile' => $outputFile,
            'nbTotal' => $nbTotal,
            'nbConserve' => $nbConserve,
            'tolerance' => $tolerance,
            'data' => $data,
            'indexesConserves' => $indexesConserves,
        ]);
    }
}
