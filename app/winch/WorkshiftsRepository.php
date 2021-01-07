<?php
namespace App\winch;

use DB;

class WorkshiftsRepository
{

    function GetMySalonServicesActiveRequests($salonId) {
		$res = query("SELECT s.name service_name, ms.price_default, ms.duration_default, rs.desired_time, rs.created_at, rs.id,
										SECOND(rs.created_at)*10 AS limit_seconds
			FROM requests_to_salons rs
			JOIN services s ON s.id=rs.service_id
			JOIN masters_services ms ON ms.service_id=rs.service_id AND ms.person_id IS NULL AND ms.salon_id=?
			WHERE rs.status='proposed' AND rs.salon_id=? ", [$salonId, $salonId]);


		$interval = (object)['start'=>'2020-09-03 11:10', 'end'=>'2020-09-03 15:00'];
		$this->loadShifts($interval, $salonId);

		return [
			'activeRequests' => _gField($res, 'id', false),
			'vacancyInShifts' => $this->findPlaceForIntervalInShifts($interval, 11),
		];
    }

    //////////////////////////////////////////

	function impositionIntervals($schedule1, $schedule2, $exceptionOnConflict=false) { // наложение

		$arr = array_merge($schedule1, $schedule2);

		usort($arr, function($a, $b) {
			return $a->from == $b->from ? 0 : ($a->from > $b->from ? 1 : -1);
		});

		foreach($arr as $k => $el) {
			if (isset($arr[$k+1]) && $el->to > $arr[$k+1]->from ) { // обрабатываем конфликт
				if ($exceptionOnConflict) {
					throw new \DomainException('conflict: ' . $el->id . ' and ' . $arr[$k+1]->id);
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

	private function testToSlove($schedule, $begin, $duration) { // проверить возможно ли впихнуть услугу в данное время
		//var_dump($begin);
		//var_dump($duration);
		//var_dump([(object)['f' => $begin, 't' => $begin + $duration], ]);
		try {
			$this->impositionIntervals($schedule, [(object)['from' => $begin, 'to' => $begin + $duration]], true);
			return true;
		}
		catch (\DomainException $e) {
			return false;
		}
	}

	function findPlaceForIntervalInSchdule($schedule, $interval, $duration) { // ищем, куда можно впихнуть услугу, заданной длины.
		//var_dump($schedule);
		//var_dump($duration);

		$res = [];
		if ($this->testToSlove($schedule, $interval->start, $duration)) {
			$res[] = date('d.m.Y H:i:s', $interval->start * 60);
			//$res[] = $interval->start;
		}
		foreach ($schedule as $k => $el) {
			//var_dump($el);
			if ($el->to > $interval->start &&  $el->to < $interval->end ) { // момент окончания очередной услуги попадает в заданый интервал
				if ($this->testToSlove($schedule, $el->to, $duration)) {
					$res[] = date('d.m.Y H:i:s',  $el->to * 60);
				}

				// пытаемся прибовлять интервал, проверяя что расход времени не вышел за пределы: либо интервала, либо след. услуги, либо конца смены.
				/*
				$newTime = $el->to + 10;
				while ($newTime < $interval->end && ($schedule[$k+1] && $newTime < $schedule[$k+1]->from) ) {
					if ($this->testToSlove($schedule, $newTime, $duration)) {
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

	}

	function findPlaceForIntervalInShifts($interval, $serviceId) {
		$interval->start = strtotime($interval->start) / 60;
		$interval->end = strtotime($interval->end) / 60;
		$ret = [];
		foreach($this->workshifts as $shiftId => $shift) {
			//var_dump($interval);

			$duration = 20;
			//$duration = getServiceDurationByMaster($serviceId, $shift->masterId);

			$ret[$shiftId] = $this->findPlaceForIntervalInSchdule($shift['schedule'], $interval, $duration);
		}
		return $ret;
	}

	function loadShifts($interval, $salonId) {
		// TODO: Возможно лучьше будет загружать только смены мастеров, которые реально могут выполнять требуюмую услугу.

		$workshifts = DB::connection('mysql2')->select("SELECT id, master_id, ADDTIME(date_begin, time_begin) shift_begin, duration_minutes
			FROM workshifts
			WHERE salon_id=?
				AND ADDTIME(date_begin, time_begin) < ?
				AND TIMESTAMPADD(MINUTE, duration_minutes, ADDTIME(date_begin, time_begin)) > ? ", [$salonId, $interval->end, $interval->start]);

		$this->workshifts = _gField($workshifts, 'id');
		foreach ($this->workshifts as &$ws) {
			$ws['schedule'] = [];
		}
		//var_dump($this->workshifts);

		$ss = query("SELECT ms.id, ms.shift_id, ms.service_id, ms.begin_minutes, ms.duration_minutes
			FROM masters_schedule ms
			WHERE ms.shift_id IN (ph0)
			ORDER BY ms.begin_minutes", [array_keys($this->workshifts)]);

		foreach ($ss AS $s)	{
			$s->shift_begin_minutes = strtotime($this->workshifts[$s->shift_id]['shift_begin'])/60;
			$s->from = $s->shift_begin_minutes + $s->begin_minutes;
			$s->to = $s->from + $s->duration_minutes;
			$this->workshifts[$s->shift_id]['schedule'][] = $s;
		}

	}

}