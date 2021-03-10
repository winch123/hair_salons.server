<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\winch\YandexMapsParser;
use App\winch\SalonAdmin;

class ProcessGeodata extends Controller
{
    public function __construct(YandexMapsParser $YandexMapsParser)
    {
        $this->YandexMapsParser = $YandexMapsParser;
    }

    public function YandexMapsFirmsSave()
    {
        header("Access-Control-Allow-Origin: *");

        //$d = json_decode($_POST['yandexData']);
        if (empty($_POST['yandexData']['items'])) {
            return;
        }
        //mylog($_POST['yandexData']);
        $d = json_encode($_POST['yandexData']['items']);
        //mylog($d);
        $d = json_decode($d);

        //$p = scandir("/tmp");
        //mkdir('/tmp/2');

        //$r = file_put_contents('/home/winch/1/test1', 'hhhh');
        $process_count = $this->YandexMapsParser->process_firms($d);

        return ['process_count' => $process_count];
    }

    ////////////////////////////////

    public function FindSalonsByStreetName()
    {
        $firms = query("SELECT id, name, locality, street, house
            FROM {firms}
            WHERE street LIKE ? OR street LIKE ? ", ["$_GET[street_name]%", "% $_GET[street_name]%"]);
        return ['firms' => $firms];
    }

    public function addMeToSalon(Request $request, SalonAdmin $SalonAdmin)
    {
        return ['result' => $SalonAdmin->addMeToSalon($request->firmId)];
    }

    public function getMySalons(Request $request, SalonAdmin $SalonAdmin)
    {
        //return $request->user();
        return $SalonAdmin->getMySalons();
    }

}
