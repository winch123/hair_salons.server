<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\winch\WorkshiftsRepository;

class SalonsForVisitors extends Controller
{
    public function __construct(WorkshiftsRepository $WorkshiftsRepository)
    {
        $this->WorkshiftsRepository = $WorkshiftsRepository;
    }

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

		for ($i=0; $i<60; $i++) {
			sleep(1);
			$r = current(query("SELECT status FROM requests_to_salons WHERE id=?", [$id]));
			if ($r->status != 'proposed') {
				return ['message' => [
						'accepted'		=> 'принято',
						'rejected'		=> 'отказ',
						'conflicting'	=> 'время уже занято',
					][$r->status], 'id' => $id];
			}
		}
		query("UPDATE requests_to_salons SET status='timeout' WHERE id=?", [$id]);
		return ['message' => 'ответ не получен', 'id' => $id];
    }

    /*
    * Возвращает временные точки с шагом 10 мин, на которые можно записаться для получения определенной услуги на определенную дату в определенном салоне.
    */
    function getUnoccupiedSchedule()
    {
		$p = (object) $_REQUEST;

		$interval = (object)['start'=>$p->date.' 06:00', 'end'=>$p->date.' 21:00'];
		$workshifts = $this->WorkshiftsRepository->loadShiftsByIntervalAndService($p->salonId, $interval, $p->serviceId);
		$min_t = $max_t = null;

		foreach ($workshifts as &$ws) {
			if (!isset($min_t) || $min_t > $ws['from'] ) {
				$min_t = $ws['from'];
			}
			if (!isset($max_t) || $max_t < $ws['to'] ) {
				$max_t = $ws['to'];
			}
			$ws['unoccupied'] = [];
			for ($i = $ws['from']; $i < $ws['to']; $i += 10) {
				$ws['unoccupied'][$i] = ['free' => true];
			}
			//var_dump($ws);

			foreach ($ws['schedule'] as $s) {
				//var_dump($s);
				for ($i = 0; $i < ceil($s->duration_minutes/10); $i++) {
					//var_dump($s->from, $i, 10, $ws['from'] + $i * 10);
					$ws['unoccupied'][ceil($s->from/10) * 10 + $i * 10]['free'] = false;
				}
			}
		}
		unset($ws);
		//var_dump($min_t, $max_t);
		//return $workshifts;
		//foreach ($workshifts as $k => $ws) {
		//	var_dump([$k,$ws['id'] ]);
		//}

		// склейка свободных промежутков
		$res = [];
		for ($i = $min_t; $i < $max_t; $i += 10) {
			$res[$i] = ['free' => false];
			foreach ($workshifts as $ws) {
				//var_dump([$i, $ws['id'], isset($ws['unoccupied'][$i])]);
				if (isset($ws['unoccupied'][$i]) && $ws['unoccupied'][$i]['free']) {
					$res[$i]['free'] = true;
					break;
				}
			}
		}
		return $res;
    }

}
