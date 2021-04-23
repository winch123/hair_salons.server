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
        return view('page1', []);
    }

    function getServicesList() {
        $params = [];
        $sql = "SELECT s.id, s.name, s.parent_service, count(ss.salon_id) count_salons
            FROM salons_services ss
            JOIN services s ON ss.service_id=s.id
            GROUP BY s.id, s.name, s.parent_service ";

        if (isset($_REQUEST['salons'])) {
            $sql .= " WHERE ss.salon_id IN (ph0) ";
            $params[] = $_REQUEST['salons'];
        }

        $services = query($sql, $params);
        $cats = query("SELECT id, name FROM services WHERE parent_service IS NULL");
        $cats = _gField($cats);

        foreach ($services as $serv) {
            $cats[$serv->parent_service]['services'][] = $serv;
        }
        foreach ($cats as $k => $v) { // удаление пустых категорий
            if (empty($v['services'])) {
                unset($cats[$k]);
            }
        }

        return $cats;
    }

    function getSalonsPerformingService()
    {
        $salons = query("SELECT s.id, s.name, ss.price_default, ss.duration_default FROM salons_services ss
            JOIN salons s ON s.id=ss.salon_id
            WHERE ss.service_id=?", [$_REQUEST['service_id']]);

        return _gField($salons);
    }

	public function sendRequestToSalon()
	{
		$p = (object) $_REQUEST;
		$id = query("INSERT INTO requests_to_salons (salon_id, service_id, desired_time, comment)
				VALUES (?,?,?,?)", [$p->salon_id, $p->service_id, $p->desired_time, $p->comment]);

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
		$workshifts = $this->WorkshiftsRepository->loadShiftsByIntervalAndService([$p->salonId], $interval, $p->serviceId);
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
