<?php

namespace App\Controller;

use App\Service\FirebaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilder;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class MainController extends AbstractController
{
    #[Route('/main', name: 'app_main')]
    public function index(FirebaseService $fs, ChartBuilderInterface $chartBuilder): Response
    {
        $lastData = $fs->getLast('/pomiar');
        $status = $this->getStatus($fs);        
        $co = $this->airqualityThresholds($fs);
        $gases = $this->getGases($fs);
        $last24Chart = $this->generateChart($chartBuilder, $fs);
        $meanTemp24h = $this->mean24hTemperature($fs);
        $minTemp24h = $this->min24hTemperature($fs);
        $maxTemp24h = $this->max24hTemperature($fs);

        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'lastData' => $lastData,
            'status' => $status,
            'co' => $co,
            'gases' => $gases,
            'last24Chart' => $last24Chart,
            'mean' => $meanTemp24h,
            'min' => $minTemp24h,
            'max' => $maxTemp24h,
        ]);
    }

    private function getStatus(FirebaseService $fs)
    {
        $lastDate = $fs->getLastDate('/pomiar')->format('Y-m-d');
        $currentDate = (new \DateTime())->format('Y-m-d');
        
        $lastHour = $fs->getLastHour('/pomiar');

        $currentTime = (new \DateTime());
        $currentTime->setTimezone(new \DateTimeZone('Europe/Warsaw'));
        $currentTimeSub = clone $currentTime;
        $currentTimeAdd = clone $currentTime;
        $currentTimeSub->sub(new \DateInterval('PT15M'));
        $currentTimeAdd->add(new \DateInterval('PT15M'));

        if($lastDate === $currentDate && ($lastHour->format('H:i') >= $currentTimeSub->format('H:i') && $lastHour->format('H:i') <= $currentTimeAdd->format('H:i'))) {
            return 'online';
        } else {
            return 'offline';
        }
    }

    private function calculateBaseCO(FirebaseService $fs): float
    {
        $measurements = $fs->list('/pomiar');
        $measurements = array_reverse($measurements);

        $baseArray = [];
        $count = 0;
        
        //wybranie dni zawierających łącznie co najmniej 13 pomiarów
        foreach($measurements as $key => $value){
            $baseArray[$key] = $value;
            $count += count($value);
            if($count >= 13){
                break;
            }
        }

        $baseArraySliced = [];
        $counter = 12;
        //przycięcie tablicy do 12 pomiarów
        foreach($baseArray as $key => $value){
            $value = array_reverse($value);

            if($counter > 0){

                if(count($value) > 12){
                    $baseArraySliced = array_merge($baseArraySliced, array_slice($value, 1, $counter));
                    $counter -= count($baseArraySliced);
                    break;
                }
                
                else{
                    $counter -= count($value);
                    $baseArraySliced = array_merge($baseArraySliced, $value);
                }
            }
        }
        $baseArrayValues = [];
        foreach($baseArraySliced as $measurement){
            $baseArrayValues[] = $measurement['airquality'];
        }
        
        return $this->calculateMedian($baseArrayValues);
        
    }

    private function calculateMedian(array $arr): float
    {
        sort($arr);
        $count = count($arr);
        $middle = floor(($count-1)/2);

        if($count % 2) {
            return (float) $arr[$middle];
        } else {
            return (float) ($arr[$middle] + $arr[$middle + 1]) / 2.0;
        }
    }

    private function airqualityThresholds(FirebaseService $fs): string
    {
        $base = $this->calculateBaseCO($fs);
        $aq = $fs->getLast('/pomiar');

        if($aq['airquality'] >= 2*$base) {
            $t = 'Wykryto';
        }
        else {
            $t = 'Nie wykryto';
        }

        return $t;
    }

    private function getGases(FirebaseService $fs): string
    {
        $lastData = $fs->getLast('/pomiar');
        if($lastData['gases'] == 0) {
            $gases = 'Wykryto';
        }
        else {
            $gases = 'Nie wykryto';
        }

        return $gases;
    }

    private function generateChart(ChartBuilderInterface $chartBuilder, FirebaseService $fs): Chart
    {

        $data = $fs->getLast24h('/pomiar');

        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => array_keys($data),
            'datasets' => [
                [
                    'label' => 'Temperatura',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'data' => array_values(array_column($data, 'temperature')),
                    'borderColor' => '#FFB000',
                    'borderWidth' => 2,
                    'tension' => 0.4,
                    'fill' => false,
                    'shadowColor' => 'rgba(255, 176, 0, 0.4)',
                    'shadowBlur' => 10,
                    'pointRadius' => 5,
                    'pointHoverRadius' => 7,
                    'pointBackgroundColor' => '#1f2933', // środek (ciemne tło)
                    'pointBorderColor' => '#FFB000',     // obwódka
                    'pointBorderWidth' => 3,
                ],
            ],
        ]);

      $chart->setOptions([
        'plugins' => [
            'legend' => [
                'display' => false,
            ],
        ],
        'maintainAspectRatio' => false,
        'scales' => [
            'x' => [
                'grid' => [
                'color' => 'rgba(255,255,255,0.2)',
                'borderDash' => [4, 6],
                ],
                'ticks' => [
                    'display' => false,
                ],
            ],
            'y' => [
                'ticks' => [
                    'stepSize' => 5,
                ],
            ],
        ],
    ]);
        return $chart;
    }


    private function mean24hTemperature(FirebaseService $fs): float
    {
        $data = $fs->getLast24h('/pomiar');
        $temperatures = array_values(array_column($data, 'temperature'));
        $mean = array_sum($temperatures) / count($temperatures);
        return round($mean, 1);
    }

    private function min24hTemperature(FirebaseService $fs): float
    {
        $data = $fs->getLast24h('/pomiar');
        $temperatures = array_values(array_column($data, 'temperature'));
        return min($temperatures);
    }

    private function max24hTemperature(FirebaseService $fs): float
    {
        $data = $fs->getLast24h('/pomiar');
        $temperatures = array_values(array_column($data, 'temperature'));
        return max($temperatures);
    }

}