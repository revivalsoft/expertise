<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class RegressionLineaireController extends AbstractController
{
    #[Route('/regression', name: 'app_regression', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $file = $request->query->get('file'); // nom du fichier CSV
        $m2 = $request->query->get('surface');
        $resultat = null;
      
       

        if ($file && $m2) {
            // Chargement et calcul
            $csvPath = $this->getParameter('kernel.project_dir').'/public/uploads/'.$file;
            if (file_exists($csvPath)) {
                $data = array_map('str_getcsv', file($csvPath));

                //  les colonnes sont : prix, surface
                $prix = [];
                $surface = [];

                foreach ($data as $row) {
                    if (count($row) >= 2) {
                        $surface[] = (float) $row[0];
                        $prix[] = (float) $row[1];
                    }
                }

                $n = count($prix);
                if ($n === 0) {
                    return new Response("Aucune donnée dans le fichier.", 400);
                }
        
                // Méthode directe
                $sumX = array_sum($surface);
                $sumY = array_sum($prix);
                $sumXY = array_sum(array_map(fn($x, $y) => $x * $y, $surface, $prix));
                $sumX2 = array_sum(array_map(fn($x) => $x * $x, $surface));
        
                $a1 = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
                $b1 = ($sumY - $a1 * $sumX) / $n;
        
                // Méthode statistique
                $statResult = $this->calculateLinearRegression($surface, $prix);
                $a2 = $statResult['slope'];
                $b2 = $statResult['intercept'];
                $r2 = $statResult['r_squared'];
        
                // Méthode robuste
                $robust = $this->calculateRobustRegression($surface, $prix);
                $a3 = $robust['slope'];
                $b3 = $robust['intercept'];


                $powerResult = $this->calculatePowerRegression($surface, $prix);
                $a5 = $powerResult['a'];
                $b5 = $powerResult['b'];
                
                $xInput = $m2;
                
                $estimation6 = null;
                if ($xInput !== null && $xInput > 0) {
                    $estimation6 = round($a5 * pow($xInput , $b5), 0);
                }
                // Estimations
                
                $estimation1 = $estimation2 = $estimation3 =$estimation4=$estimation5 =$estimationMoyenne = null;
             
                if ($xInput !== null) {
                    $x = floatval($xInput); 
                    $estimation1 = round($a1 * $x + $b1, 0);
                    $estimation2 = round($a2 * $x + $b2, 0);
                    $estimation3 = round($a3 * $x + $b3, 0);
                    $estimation4 = round($this->estimateByLocalWeightedRegression($surface, $prix, floatval($m2)));

                    $logResult = $this->calculateLogarithmicRegression($surface, $prix);
                    $a4 = $logResult['slope'];
                    $b4 = $logResult['intercept'];
                    $estimation5 = round($a4 * log($x) + $b4, 0);


                    $estimationMoyenne = round(($estimation1 + $estimation2 + $estimation3 + $estimation4+ $estimation5+$estimation6) / 6, 0);
                }
       
                return $this->render('regression_lineaire/index.html.twig', [
                    'a1' => $a1,
                    'b1' => $b1,
                    'a2' => $a2,
                    'b2' => $b2,
                    'r2' => $r2,
                    'a3' => $a3,
                    'b3' => $b3,
                    'estimation1' => $estimation1,
                    'estimation2' => $estimation2,
                    'estimation3' => $estimation3,
                    'estimation4' => $estimation4,
                    'estimation5' => $estimation5,
                    'estimation6' => $estimation6,
                    'a4' => $a4,
                    'b4' => $b4,
                    'a5' => $a5,
                    'b5' => $b5,
                    'estimationMoyenne' => $estimationMoyenne,
                    'fichier' => $file,
                    'surface' => $m2
                ]);

            }
        }
        else{
            return $this->render('regression_lineaire/index.html.twig', [
                'fichier' => $file
            ]);
                

        }
   
    }

    private function calculateLinearRegression(array $x, array $y): array
    {
        $n = count($x);
        if ($n !== count($y) || $n === 0) {
            throw new \InvalidArgumentException("Les tableaux doivent être de même taille et non vides.");
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] ** 2;
            $sumY2 += $y[$i] ** 2;
        }

        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = $n * $sumX2 - $sumX ** 2;

        if ($denominator == 0) {
            throw new \RuntimeException("Division par zéro dans le calcul de la pente.");
        }

        $a = $numerator / $denominator;
        $b = ($sumY - $a * $sumX) / $n;

        $rNumerator = $n * $sumXY - $sumX * $sumY;
        $rDenominator = sqrt(($n * $sumX2 - $sumX ** 2) * ($n * $sumY2 - $sumY ** 2));
        $r = $rDenominator != 0 ? $rNumerator / $rDenominator : 0;

        return [
            'slope' => $a,
            'intercept' => $b,
            'correlation' => $r,
            'r_squared' => $r ** 2,
        ];
    }

    private function calculateRobustRegression(array $x, array $y): array
    {
        $n = count($x);
        if ($n !== count($y) || $n < 2) {
            throw new \InvalidArgumentException("Il faut au moins deux points pour estimer la pente.");
        }

        $slopes = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($x[$j] !== $x[$i]) {
                    $slopes[] = ($y[$j] - $y[$i]) / ($x[$j] - $x[$i]);
                }
            }
        }

        sort($slopes);
        $slope = $slopes[intdiv(count($slopes), 2)];

        $intercepts = array_map(fn($xi, $yi) => $yi - $slope * $xi, $x, $y);
        sort($intercepts);
        $intercept = $intercepts[intdiv(count($intercepts), 2)];

        return [
            'slope' => $slope,
            'intercept' => $intercept
        ];
    }

    private function estimateByLocalWeightedRegression(array $x, array $y, float $xInput): float
{
    $n = count($x);
    if ($n === 0 || count($y) !== $n) {
        throw new \InvalidArgumentException("Les tableaux doivent être non vides et de même taille.");
    }

    // Étape 1 : éliminer les valeurs aberrantes (prix par m² trop éloignés)
    $pricesPerM2 = array_map(fn($p, $s) => $s > 0 ? $p / $s : 0, $y, $x);
    $q1 = $this->percentile($pricesPerM2, 25);
    $q3 = $this->percentile($pricesPerM2, 75);
    $iqr = $q3 - $q1;
    $lower = $q1 - 1.5 * $iqr;
    $upper = $q3 + 1.5 * $iqr;

    $filteredX = [];
    $filteredY = [];

    foreach ($x as $i => $xi) {
        $ratio = $x[$i] > 0 ? $y[$i] / $x[$i] : 0;
        if ($ratio >= $lower && $ratio <= $upper) {
            $filteredX[] = $x[$i];
            $filteredY[] = $y[$i];
        }
    }

    // Étape 2 : calculer les poids en fonction de la proximité de la surface
    $weights = [];
    foreach ($filteredX as $xi) {
        $dist = abs($xi - $xInput);
        $weights[] = 1 / (1 + $dist); // plus proche ⇒ poids plus fort
    }

    // Étape 3 : calcul de la pente et de l’interception pondérées
    $sumW = array_sum($weights);
    $meanX = array_sum(array_map(fn($w, $xi) => $w * $xi, $weights, $filteredX)) / $sumW;
    $meanY = array_sum(array_map(fn($w, $yi) => $w * $yi, $weights, $filteredY)) / $sumW;

    $num = 0;
    $den = 0;

    for ($i = 0; $i < count($filteredX); $i++) {
        $dx = $filteredX[$i] - $meanX;
        $dy = $filteredY[$i] - $meanY;
        $num += $weights[$i] * $dx * $dy;
        $den += $weights[$i] * $dx * $dx;
    }

    $a = $den != 0 ? $num / $den : 0;
    $b = $meanY - $a * $meanX;

    return round($a * $xInput + $b, 2);
}


private function percentile(array $values, float $percent): float
{
    sort($values);
    $index = ($percent / 100) * (count($values) - 1);
    $floor = (int) floor($index);
    $ceil = (int) ceil($index);
    if ($floor === $ceil) {
        return $values[$floor];
    }
    return $values[$floor] + ($index - $floor) * ($values[$ceil] - $values[$floor]);
}

private function calculateLogarithmicRegression(array $x, array $y): array
{
    $logX = [];
    $filteredY = [];

    foreach ($x as $index => $val) {
        if ($val > 0) {
            $logX[] = log($val);
            $filteredY[] = $y[$index];
        }
    }

    $n = count($logX);
    if ($n === 0) {
        throw new \RuntimeException("Aucune valeur de surface strictement positive pour la régression logarithmique.");
    }

    $sumLogX = array_sum($logX);
    $sumY = array_sum($filteredY);
    $sumLogXY = array_sum(array_map(fn($lx, $y) => $lx * $y, $logX, $filteredY));
    $sumLogX2 = array_sum(array_map(fn($lx) => $lx * $lx, $logX));

    $slope = ($n * $sumLogXY - $sumLogX * $sumY) / ($n * $sumLogX2 - $sumLogX ** 2);
    $intercept = ($sumY - $slope * $sumLogX) / $n;

    return [
        'slope' => $slope,
        'intercept' => $intercept
    ];
}

private function calculatePowerRegression(array $x, array $y): array
{
    $logX = [];
    $logY = [];

    foreach ($x as $i => $xi) {
        if ($xi > 0 && $y[$i] > 0) {
            $logX[] = log($xi);
            $logY[] = log($y[$i]);
        }
    }

    $n = count($logX);
    if ($n === 0) {
        throw new \RuntimeException("Pas de données valides pour la régression puissance.");
    }

    $sumLogX = array_sum($logX);
    $sumLogY = array_sum($logY);
    $sumLogX2 = array_sum(array_map(fn($v) => $v ** 2, $logX));
    $sumLogXY = array_sum(array_map(fn($lx, $ly) => $lx * $ly, $logX, $logY));

    $b = ($n * $sumLogXY - $sumLogX * $sumLogY) / ($n * $sumLogX2 - $sumLogX ** 2);
    $logA = ($sumLogY - $b * $sumLogX) / $n;
    $a = exp($logA);

    return [
        'a' => $a,
        'b' => $b,
    ];
}

}
