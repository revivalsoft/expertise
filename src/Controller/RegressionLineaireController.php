<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RegressionLineaireController extends AbstractController
{
    #[Route('/regression', name: 'app_regression', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $file = trim($request->query->get('file') ?? '');
        $m2 = $request->get('surface');
        $checkedIndexes = $request->request->all('checked') ?? [];

        $surface = [];
        $prix = [];

        if ($file) {
            $csvPath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $file;

            if (!file_exists($csvPath)) {
                throw $this->createNotFoundException('Fichier CSV introuvable.');
            }

            $handle = fopen($csvPath, 'r');
            if ($handle !== false) {
                $lineIndex = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($data) < 2) continue;
                    if ($lineIndex === 0 && (!is_numeric($data[0]) || !is_numeric($data[1]))) {
                        $lineIndex++;
                        continue;
                    }
                    $surface[] = floatval($data[0]);
                    $prix[] = floatval($data[1]);
                    $lineIndex++;
                }
                fclose($handle);
            }
        }

        $filteredSurface = [];
        $filteredPrix = [];
        foreach ($checkedIndexes as $index) {
            if (isset($surface[$index], $prix[$index])) {
                $filteredSurface[] = $surface[$index];
                $filteredPrix[] = $prix[$index];
            }
        }

        if (empty($checkedIndexes)) {
            $filteredSurface = $surface;
            $filteredPrix = $prix;
        }

        $estimations = null;
        $scores = null;
        if ($m2 && count($filteredSurface) > 1) {
            $m2Value = floatval($m2);
            $estimations = [
                'linéaire' => $this->estimationLineaire($filteredSurface, $filteredPrix, $m2Value),
                'logarithmique' => $this->estimationLog($filteredSurface, $filteredPrix, $m2Value),
                'puissance' => $this->estimationPower($filteredSurface, $filteredPrix, $m2Value),
                'lowess' => $this->estimationLowess($filteredSurface, $filteredPrix, $m2Value),
            ];

            $yHatLinear = [];
            foreach ($filteredSurface as $xi) {
                $yHatLinear[] = $this->estimationLineaire($filteredSurface, $filteredPrix, $xi);
            }

            $scores = [
                'r2_lineaire' => $this->rSquared($filteredPrix, $yHatLinear),
                'rmse_lineaire' => $this->rmse($filteredPrix, $yHatLinear),
            ];
        }

        $checkedIndexes = array_keys($filteredSurface);

        $context = [
            'file' => $file,
            'm2' => $m2,
            'surface' => $surface,
            'prix' => $prix,
            'filteredSurface' => $filteredSurface,
            'filteredPrix' => $filteredPrix,
            'estimations' => $estimations,
            'checkedIndexes' => $checkedIndexes,
            'scores' => $scores,
            'graphData' => [
            'surface' => $filteredSurface,
            'prix' => $filteredPrix,
            'slope' => $slope ?? null,
            'intercept' => $intercept ?? null,
            ],
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('regression/_results.html.twig', $context);
        }

        return $this->render('regression/index.html.twig', $context);
    }

    private function estimationLineaire(array $x, array $y, float $xInput): float
    {
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = array_sum(array_map(fn ($xi, $yi) => $xi * $yi, $x, $y));
        $sumX2 = array_sum(array_map(fn ($xi) => $xi ** 2, $x));

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX ** 2);
        $intercept = ($sumY - $slope * $sumX) / $n;

        return round($intercept + $slope * $xInput, 2);
    }

    private function estimationLog(array $x, array $y, float $xInput): float
    {
        $xLog = array_map(fn ($v) => log($v), $x);
        return $this->estimationLineaire($xLog, $y, log($xInput));
    }

    private function estimationPower(array $x, array $y, float $xInput): float
    {
        $xLog = array_map(fn ($v) => log($v), $x);
        $yLog = array_map(fn ($v) => log($v), $y);
        $logEstimate = $this->estimationLineaire($xLog, $yLog, log($xInput));
        return round(exp($logEstimate), 2);
    }

    private function estimationLowess(array $x, array $y, float $xInput, float $bandwidth = 0.3): float
    {
        $n = count($x);
        $distances = array_map(fn($xi) => abs($xi - $xInput), $x);
        $sorted = $distances;
        asort($sorted);
        $k = (int) round($bandwidth * $n);

        $indexes = array_slice(array_keys($sorted), 0, $k, true);
        $weights = [];
        foreach ($indexes as $i) {
            $d = $distances[$i];
            $maxD = $distances[$indexes[array_key_last($indexes)]] ?? 1;
            $w = $maxD > 0 ? pow(1 - pow($d / $maxD, 3), 3) : 1;
            $weights[$i] = $w;
        }

        $weightedSum = 0;
        $weightTotal = 0;
        foreach ($weights as $i => $w) {
            $weightedSum += $w * $y[$i];
            $weightTotal += $w;
        }

        return round($weightTotal > 0 ? $weightedSum / $weightTotal : 0, 2);
    }

    private function rSquared(array $y, array $yPred): float
    {
        $meanY = array_sum($y) / count($y);
        $ssTot = array_sum(array_map(fn($yi) => ($yi - $meanY) ** 2, $y));
        $ssRes = array_sum(array_map(fn($yi, $yhat) => ($yi - $yhat) ** 2, $y, $yPred));
        return round(1 - ($ssRes / $ssTot), 4);
    }

    private function rmse(array $y, array $yPred): float
    {
        $n = count($y);
        $squaredErrors = array_map(fn($yi, $yhat) => ($yi - $yhat) ** 2, $y, $yPred);
        return round(sqrt(array_sum($squaredErrors) / $n), 2);
    }
}
