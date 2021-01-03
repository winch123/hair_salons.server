<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SalonsForVisitors extends Controller
{
    public function test()
    {
        $services = query("SELECT ms.salon_id, s.id, s.name
            FROM masters_services ms
            JOIN services s ON ms.service_id=s.id
            WHERE ms.salon_id=2 ");
        return view('page1', ['services' => $services]);
    }

    public function sendRequestToSalon()
    {
	$p = (object) $_REQUEST;
	$id = query("INSERT INTO requests_to_salons (salon_id, service_id, desired_time)
	      VALUES (?,?,?)", [$p->salon_id, $p->service_id, $p->desired_time]);

        for ($i=0; $i<2; $i++) {
            sleep(1);
            $r = query("SELECT * FROM requests_to_salons WHERE id=?", [$id]);
        }
        return ['message' => 'нет, ни-за-что!', 'id' => $id];
    }

}
