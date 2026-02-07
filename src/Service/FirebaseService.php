<?php

namespace App\Service;

use DateTime;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;
use Symfony\Component\HttpKernel\KernelInterface;

class FirebaseService
{
    private Database $database;

    public function __construct(KernelInterface $kernel)
    {
        $relativePath = $_ENV['FIREBASE_CREDENTIALS'];
        $absolutePath = $kernel->getProjectDir() . '/' . $_ENV['FIREBASE_CREDENTIALS'];

        if (!file_exists($absolutePath) || !is_readable($absolutePath)) {
            throw new \RuntimeException("Plik Firebase credentials nieczytelny lub nie istnieje: $absolutePath");
        }

        $dbUri = $_ENV['FIREBASE_DB_URI'];

        $factory = (new Factory())
            ->withServiceAccount($absolutePath)
            ->withDatabaseUri($dbUri);

        $this->database = $factory->createDatabase();
    }
    

    public function get(string $path): mixed
    {
        return $this->database->getReference($path)->getValue();
    }

    public function set(string $path, mixed $data): void
    {
        $this->database->getReference($path)->set($data);
    }

    public function push(string $path, mixed $data): void
    {
        $this->database->getReference($path)->push($data);
    }

    public function list(string $path): array
    {
        $value = $this->database->getReference($path)->getValue();
        return is_array($value) ? $value : [];
    }

    public function getLast(string $path): array
    {
        $value = $this->database->getReference($path)->getValue();
        $data = array_reverse($value);
        $today = $data[array_key_first($data)];
        $last = $today[array_key_last($today)];

        return is_array($last) ? $last : [];
    }
    public function getLastDate(string $path):DateTime
    {
        $value = $this->database->getReference($path)->getValue();
        $data = array_reverse($value);

        $date = array_keys($data);
        $date = $date[0];
        $date = new DateTime($date);

        return $date;
    }
    public function getLastHour(string $path):DateTime
    {
        $value = $this->database->getReference($path)->getValue();
        $data = array_reverse($value);
        $today = $data[array_key_first($data)];

        $hour = array_reverse($today);
        $hour = array_keys($hour);
        $hour = $hour[0];
        $hour = new DateTime($hour);
        return $hour;
    }

    public function getLast24h(string $path): array
    {
        $value = $this->database->getReference($path)->getValue();
        $data = array_reverse($value);
        $data = array_slice($data, 0, 2);

        $currentDate = new DateTime();
        // $currentDate = new DateTime()->setDate('2025', '09', '11');
        // $currentDateTime = new DateTime()->setDate('2025', '09', '11')->setTime('14', '00');

        $currentDateTime = new DateTime();
        $currentDateTime->setTimezone(new \DateTimeZone('Europe/Warsaw'));
        $currentDate = $currentDateTime->format('Y-m-d');
        $currentHour = $currentDateTime->format('H:i');

        $rangeStart = (new DateTime($currentDate . ' ' . $currentHour))->sub(new \DateInterval('PT24H'));
        $rangeEnd = new DateTime($currentDate . ' ' . $currentHour);
        
        $dataKeys = array_keys($data);

        $last24h = [];

        foreach ($dataKeys as $key){
            if($key === $rangeEnd->format('Y-m-d')){
                foreach(array_reverse($data[$key]) as $key2 => $value){
                    if($key2 <= $rangeEnd->format('H:i')){
                        $last24h[$key2] = $value;
                    }
                }   
            }
            if($key === $rangeStart->format('Y-m-d')){
                foreach(array_reverse($data[$key]) as $key2 => $value){
                    if($key2 >= $rangeStart->format('H:i')){
                        $last24h[($key2)] = $value;
                    }
                }
            }
            
        }
        return array_reverse($last24h);
    }
}

