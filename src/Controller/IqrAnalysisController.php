<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IqrAnalysisController extends AbstractController
{
    #[Route('/iqr-analysis', name: 'iqr_analysis')]
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
        $selectedPath = $selectedFile && isset($fileChoices[$selectedFile]) ? $fileChoices[$selectedFile] : null;

        $result = null;

        if ($selectedPath && file_exists($selectedPath)) {
            $rows = array_map('str_getcsv', file($selectedPath));
            $headers = array_map('trim', array_shift($rows));

            $data = [];
            foreach ($rows as $row) {
                if (count($row) < 2 || !is_numeric($row[0]) || !is_numeric($row[1])) continue;
                $surface = floatval(str_replace(',', '.', $row[0]));
                $prix = floatval(str_replace(',', '.', $row[1]));
                $prixM2 = $surface > 0 ? $prix / $surface : null;
                $data[] = ['surface' => $surface, 'prix' => $prix, 'prix_m2' => $prixM2];
            }

            // Extraction des prix/m² valides uniquement
            $prixM2Array = array_filter(array_column($data, 'prix_m2'), fn($v) => is_numeric($v));
            sort($prixM2Array);

            if (!empty($prixM2Array)) {
                $q1 = self::percentile($prixM2Array, 25);
                $q3 = self::percentile($prixM2Array, 75);
                $iqr = $q3 - $q1;
                $seuilBas = max(0, $q1 - 1.5 * $iqr);
                $seuilHaut = $q3 + 1.5 * $iqr;
                $prixMax = max($prixM2Array);

                foreach ($data as &$row) {
                    $row['outlier'] = $row['prix_m2'] < $seuilBas || $row['prix_m2'] > $seuilHaut;
                }

                $result = [
                    'data' => $data,
                    'q1' => $q1,
                    'q3' => $q3,
                    'iqr' => $iqr,
                    'seuilBas' => $seuilBas,
                    'seuilHaut' => $seuilHaut,
                    'prixMax' => $prixMax,
                ];
            }
        }

        return $this->render('iqr/index.html.twig', [
            'files' => array_keys($fileChoices),
            'selectedFile' => $selectedFile,
            'result' => $result,
        ]);
    }

    private static function percentile(array $arr, float $percent): float
    {
        $count = count($arr);
        if ($count === 0) return 0;

        $index = ($percent / 100) * ($count - 1);
        $floor = floor($index);
        $ceil = ceil($index);

        if ($floor === $ceil) {
            return $arr[$floor];
        }

        return $arr[$floor] * ($ceil - $index) + $arr[$ceil] * ($index - $floor);
    }
}
