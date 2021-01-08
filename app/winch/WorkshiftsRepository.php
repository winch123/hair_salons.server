<?php
namespace App\winch;

use DB;

class WorkshiftsRepository
{

    function GetMySalonServicesActiveRequests($salonId) {
		$res = query("SELECT s.name service_name, ms.price_default, ms.duration_default, rs.desired_time, rs.created_at, rs.id,
							rs.service_id, SECOND(rs.created_at)*10 AS limit_seconds
			FROM requests_to_salons rs
			JOIN services s ON s.id=rs.service_id
			JOIN masters_services ms ON ms.service_id=rs.service_id AND ms.person_id IS NULL AND ms.salon_id=?
			WHERE rs.status='proposed' AND rs.salon_id=? ", [$salonId, $salonId]);

		foreach($res as &$request) {
			$this->workshifts = $this->loadShifts($salonId, (object)['start'=>$request->desired_time, 'end'=>$request->desired_time]);
			$request->vacancyInShifts = $this->findPlaceForIntervalInShifts($request->desired_time, $request->service_id);
		}

		return [
			'activeRequests' => _gField($res, 'id', false),
		];
    }

    //////////////////////////////////////////

    function __construct()
    {

    }

	function impositionIntervals($schedule1, $schedule2, $exceptionOnConflict=false) { // наложение
		$arr = array_merge($schedule1, $schedule2);

		usort($arr, function($a, $b) {
			return $a->from == $b->from ? 0 : ($a->from > $b->from ? 1 : -1);
		});
		//var_dump($arr);

		foreach($arr as $k => $el) {
			//var_dump([ isset($arr[$k+1]) && $el->to > $arr[$k+1]->from && $el->from < $arr[$k+1]->to ]);
			if (isset($arr[$k+1]) && $el->to > $arr[$k+1]->from && $el->from < $arr[$k+1]->to) { // обрабатываем конфликт
				if ($exceptionOnConflict) {
					throw new \DomainException('conflict');
				}
				$arr[$k+1]->from = $el->from;

				if ($el->to > $arr[$k+1]->to) { // поглощение бОльшим промежутком
					$arr[$k+1]->to = $el->to;
				}
				unset($arr[$k]);
			}
		}
		return $arr;
	}

	function inverceInterval($from, $to, $schedule) { // инверсия
		$res = [];
		if ($from < reset($schedule)->from) {
			$res[] = (object)['from' => $from, 'to' => reset($schedule)->from];
		}
		foreach($schedule as $k => $el) {
			if (isset($schedule[$k+1])) {
				$res[] = (object)['from' => $el->to, 'to' => $schedule[$k+1]->from];
			}
		}
		if ($to > end($schedule)->t) {
			$res[] = (object)['from' => end($schedule)->to, 'to' => $to];
		}
		return $res;
	}

	function testToShove($schedule, $begin, $duration) { // проверить возможно ли впихнуть услугу в данное время
		//var_dump($begin);
		//var_dump($duration);
		//var_dump([(object)['f' => $begin, 't' => $begin + $duration], ]);
		try {
			$this->impositionIntervals($schedule, [(object)['from' => $begin, 'to' => $begin + $duration]], true);
			//var_dump('111');
			return true;
		}
		catch (\DomainException $e) {
			//var_dump('222');
			return false;
		}
	}

	function findPlaceForIntervalInSchdule($schedule, $interval, $duration) { // ищем, куда можно впихнуть услугу, заданной длины.
		//var_dump($schedule);
		//var_dump($duration);

		$res = [];
		if ($this->testToShove($schedule, $interval->start, $duration)) {
			$res[] = date('d.m.Y H:i:s', $interval->start * 60);
			//$res[] = $interval->start;
		}
		foreach ($schedule as $k => $el) {
			//var_dump($el);
			if ($el->to > $interval->start &&  $el->to < $interval->end ) { // момент окончания очередной услуги попадает в заданый интервал
				if ($this->testToShove($schedule, $el->to, $duration)) {
					$res[] = date('d.m.Y H:i:s',  $el->to * 60);
				}

				// пытаемся прибовлять интервал, проверяя что расход времени не вышел за пределы: либо интервала, либо след. услуги, либо конца смены.
				/*
				$newTime = $el->to + 10;
				while ($newTime < $interval->end && ($schedule[$k+1] && $newTime < $schedule[$k+1]->from) ) {
					if ($this->testToShove($schedule, $newTime, $duration)) {
						$res[] = $newTime;
					}
					$newTime += 10;
				};
				*/
			}
		}
		return $res;
	}

	function getServiceDurationByMaster($serviceId, $masterId) {
		return 20;
	}

	function findPlaceForIntervalInShifts($desired_time, $serviceId) {
		//$interval->start = strtotime($interval->start) / 60;
		//$interval->end = strtotime($interval->end) / 60;
		$ret = [];
		foreach($this->workshifts as $shiftId => $shift) {

			$duration = $this->getServiceDurationByMaster($serviceId, $shift['master_id']);

			//$ret[$shiftId] = $this->findPlaceForIntervalInSchdule($shift['schedule'], $interval, $duration);

			$ret[$shiftId] = [
				'masterId' => $shift['master_id'],
				'can_be_shoved' => $this->testToShove($shift['schedule'], strtotime($desired_time)/60, $duration),
			];
		}
		return $ret;
	}

	function loadShifts($salonId, $interval, $shiftId=null) {
		// TODO: Возможно лучьше будет загружать только смены мастеров, которые реально могут выполнять требуюмую услугу.
		$sql = "SELECT id, master_id, ADDTIME(date_begin, time_begin) shift_begin, duration_minutes
			FROM workshifts
			WHERE salon_id=? ";
		$params = [$salonId];

		if ($shiftId) {
			$sql .= " AND id=?";
			$params[] = $shiftId;
		}
		else {
			$sql .= " AND ADDTIME(date_begin, time_begin) < ?
				AND TIMESTAMPADD(MINUTE, duration_minutes, ADDTIME(date_begin, time_begin)) > ? ";
			$params[] = $interval->end;
			$params[] = $interval->start;
		}

		$workshifts = _gField(query($sql, $params), 'id');
		foreach ($workshifts as &$ws) {
			$ws['schedule'] = [];
		}

		if ($workshifts) {
			$ss = query("SELECT ms.id, ms.shift_id, ms.service_id, ms.begin_minutes, ms.duration_minutes
				FROM masters_schedule ms
				WHERE ms.shift_id IN (ph0)
				ORDER BY ms.begin_minutes", [array_keys($workshifts)]);

			foreach ($ss AS $s)	{
				$s->shift_begin_minutes = strtotime($workshifts[$s->shift_id]['shift_begin'])/60;
				$s->from = $s->shift_begin_minutes + $s->begin_minutes;
				$s->to = $s->from + $s->duration_minutes;
				$workshifts[$s->shift_id]['schedule'][] = $s;
			}
		}
		return $workshifts;
	}

}