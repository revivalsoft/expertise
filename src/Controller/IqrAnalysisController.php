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

    $iqrFactor = floatval($request->query->get('factor') ?? 1.5); // ✅ facteur IQR configurable
    $stdFactor = floatval($request->query->get('std') ?? 2.0);    // ✅ seuil écart-type configurable

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

        $prixM2Array = array_filter(array_column($data, 'prix_m2'), fn($v) => is_numeric($v));
        sort($prixM2Array);

        if (!empty($prixM2Array)) {
            $q1 = self::percentile($prixM2Array, 25);
            $q3 = self::percentile($prixM2Array, 75);
            $iqr = $q3 - $q1;
            $seuilBas = max(0, $q1 - $iqrFactor * $iqr);
            $seuilHaut = $q3 + $iqrFactor * $iqr;

            $mean = array_sum($prixM2Array) / count($prixM2Array);
            $stdDev = sqrt(array_sum(array_map(fn($v) => pow($v - $mean, 2), $prixM2Array)) / count($prixM2Array));
            $stdMin = max(0, $mean - $stdFactor * $stdDev);
            $stdMax = $mean + $stdFactor * $stdDev;

            // foreach ($data as &$row) {
            //     $row['iqr_outlier'] = $row['prix_m2'] < $seuilBas || $row['prix_m2'] > $seuilHaut;
            //     $row['std_outlier'] = $row['prix_m2'] < $stdMin || $row['prix_m2'] > $stdMax;
            //     $row['outlier'] = $row['iqr_outlier'] || $row['std_outlier']; // ✅ combinaison
            // }

            // 🔥 Outliers par écart à la régression puissance
            $surfaceList = array_column($data, 'surface');
            $prixList = array_column($data, 'prix');
            $regressionOutliers = $this->removeWorstOutliers($surfaceList, $prixList, 0.1); // 10 %

            foreach ($data as $i => &$row) {
                $row['iqr_outlier'] = $row['prix_m2'] < $seuilBas || $row['prix_m2'] > $seuilHaut;
                $row['std_outlier'] = $row['prix_m2'] < $stdMin || $row['prix_m2'] > $stdMax;
                $row['regression_outlier'] = in_array($i, $regressionOutliers);
                $row['outlier'] = $row['iqr_outlier'] || $row['std_outlier'] || $row['regression_outlier'];
            }

            $result = [
                'data' => $data,
                'q1' => $q1,
                'q3' => $q3,
                'iqr' => $iqr,
                'seuilBas' => $seuilBas,
                'seuilHaut' => $seuilHaut,
                'mean' => $mean,
                'stdDev' => $stdDev,
                'stdMin' => $stdMin,
                'stdMax' => $stdMax,
                'prixMax' => max($prixM2Array),
                'iqrFactor' => $iqrFactor,
                'stdFactor' => $stdFactor,
                'regressionOutliers' => $regressionOutliers,
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

    private function removeWorstOutliers(array $x, array $y, float $percentToRemove = 0.1): array
{
    $logX = array_map('log', $x);
    $logY = array_map('log', $y);

    $n = count($x);
    $sumX = array_sum($logX);
    $sumY = array_sum($logY);
    $sumXY = array_sum(array_map(fn($xi, $yi) => $xi * $yi, $logX, $logY));
    $sumX2 = array_sum(array_map(fn($xi) => $xi * $xi, $logX));

    $b = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $aLog = ($sumY - $b * $sumX) / $n;
    $a = exp($aLog);

    $residuals = [];
    foreach ($x as $i => $xi) {
        $yPred = $a * pow($xi, $b);
        $error = abs($y[$i] - $yPred);
        $residuals[$i] = $error;
    }

    arsort($residuals);
    $countToRemove = (int) floor($percentToRemove * count($x));
    $indexesToRemove = array_slice(array_keys($residuals), 0, $countToRemove);

    return $indexesToRemove;
}

}
