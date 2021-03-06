<?php
namespace App\winch;

use DB;

class WorkshiftsRepository
{

    function GetMySalonServicesActiveRequests($salonId) {
		$res = query("SELECT s.name service_name, ss.price_default, ss.duration_default, rs.desired_time, rs.id, rs.service_id,
							60 - (now()-rs.created_at) AS limit_seconds
			FROM requests_to_salons rs
			JOIN services s ON s.id=rs.service_id
			JOIN salons_services ss ON ss.service_id=rs.service_id AND ss.salon_id=?
			WHERE rs.status='proposed' AND rs.salon_id=?", [$salonId, $salonId]);

		foreach($res as &$request) {
			$interval = (object)['start'=>$request->desired_time, 'end'=>$request->desired_time];
			$this->workshifts = $this->loadShiftsByIntervalAndService([$salonId], $interval, $request->service_id);
			$request->vacancyInShifts = $this->findPlaceForIntervalInShifts($request->desired_time, $request->service_id);
			$desired_time = strtotime($request->desired_time);
			$request->title = date("H:i", $desired_time) .' '. $request->service_name;
			$request->desired_time = date("H:i d M Y", $desired_time);
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

	/*
	 *
	 * Подбираем смены, которые могут выполнить эту услугу в заданное время.
	 */
	function findPlaceForIntervalInShifts($desired_time, $serviceId) {
		//var_dump(compact('desired_time', 'serviceId'));

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

	private function loadShifts($workshifts) {
		//var_dump($workshifts);
		foreach ($workshifts as &$ws) {
			$ws['from'] = strtotime($ws['shift_begin'])/60;
			$ws['to'] = $ws['from'] + $ws['duration_minutes'];
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

	function loadShiftById($salonId, $shiftId) {
		$sql = "SELECT id, master_id, ADDTIME(date_begin, time_begin) shift_begin, duration_minutes
			FROM workshifts
			WHERE salon_id=? AND id=?";

		return $this->loadShifts(_gField(query($sql, [$salonId, $shiftId]), 'id'))[$shiftId];
	}

	/*
	*
	* Загружаем смены, которые ПЕРЕСЕКАЮТСЯ с заданным временным промежутком и могут выполнить нужную услугу.
	*/
	function loadShiftsByIntervalAndService(array $salonIds, $interval, $serviceId) {
		$sql = "SELECT
                    ws.id,
                    ws.master_id,
                    ADDTIME(ws.date_begin, ws.time_begin) shift_begin,
                    TIMESTAMPADD(MINUTE, ws.duration_minutes, ADDTIME(ws.date_begin, ws.time_begin)) shift_end,
                    ws.duration_minutes
			FROM workshifts ws
			JOIN salons_services ss ON ss.salon_id=ws.salon_id
			JOIN masters_services ms ON ss.id=salon_service_id AND ms.person_id=ws.master_id
			WHERE ws.salon_id in (ph0)
				AND ss.service_id=?
				AND ADDTIME(ws.date_begin, ws.time_begin) < ?
				AND TIMESTAMPADD(MINUTE, ws.duration_minutes, ADDTIME(ws.date_begin, ws.time_begin)) > ?";
		$params = [$salonIds, $serviceId, $interval->end, $interval->start];

		return $this->loadShifts(_gField(query($sql, $params), 'id', false));
	}

}
