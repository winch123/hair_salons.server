<?php

namespace  App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Auth;
use Gate;

use App\winch\SalonAdmin;
use App\winch\WorkshiftsRepository;
use App\winch\ImagesStore;

class SalonController extends Controller
{
    public function __construct(WorkshiftsRepository $WorkshiftsRepository)
    {
        $this->WorkshiftsRepository = $WorkshiftsRepository;
    }

    /*
     *	Возвращает существующие смены салона.
     */
    function ActualWorkshiftsGet(SalonAdmin $SalonAdmin) {
        $salonId = $_GET['salonId'];
	setlocale(LC_ALL, 'ru_RU', 'ru_RU.UTF-8', 'ru', 'russian');

        //if (! Gate::allows('is-master-of-salon', $salonId)) {
        //    return ['no access'];
        //}
        $this->authorize('master-of-salon', [$salonId, false]);

        $wss = query('SELECT ws.id AS shift_id, ws.master_id, ws.date_begin, ws.time_begin, ws.duration_minutes,
		SUM(ms.duration_minutes) AS busy_duration, COUNT(ms.id) total_services_count
            FROM workshifts AS ws
            LEFT JOIN masters_schedule AS ms ON ws.id=ms.shift_id
            WHERE ws.salon_id=:salon_id
            GROUP BY ws.id
            ORDER BY ws.date_begin', ['salon_id'=>$salonId]);

        $workshifts = [];
        for ($i = 0; $i < 6; $i++) {
            $d = strtotime("+$i day");
            $workshifts[date("Y-m-d", $d)] = ['masters' => [], 'caption' => strftime('%d %b - %a', $d)];
        }

        foreach ($wss as $ws) {
            extract((array)$ws);
            //list($date_begin, $master_id) = (array) $ws;
            unset($ws->date_begin);
            unset($ws->master_id);
            $ws->text = strftime('%H:%M', strtotime($ws->time_begin)) . ' - ' . strftime('%H:%M', strtotime("+$ws->duration_minutes min", strtotime($ws->time_begin)));
	    $ws->description = '<div>услуг: <b>' . $ws->total_services_count . '</b>, свободно: <b>' . ($ws->duration_minutes - $ws->busy_duration) . '</b> мин</div>';
            if (isset($workshifts[$date_begin])) {
                $workshifts[$date_begin]['masters'][$master_id] = $ws;
            }
        }

        $persons = $SalonAdmin->getMastersList($salonId);

        return compact('workshifts', 'persons');
    }

    /*
     * Создает смену мастера.
     */
    function CreateWorkshift() {
      /// master_id,	date_begin, time_begin,	duration_minutes
	$p = (object) $_REQUEST;
	$this->authorize('master-of-salon', [$p->salonId, true]);

	$id = DB::connection('mysql2')->table('workshifts')->insertGetId([
	    'salon_id' => $p->salonId,
	    'master_id' => $p->masterId,
	    'date_begin' => $p->dateBegin,
	    'time_begin' => $p->timeBegin,
	    'duration_minutes' => timeToMinutes($p->timeEnd) - timeToMinutes($p->timeBegin),
        ]);

	return $_REQUEST;
    }

    /*
     * Возвращает рассписание определенного мастера, в определенном салоне, на определенный день.
     */
    function ScheduleGet() {
        $ws = query('SELECT
		master_id,
                CAST(UNIX_TIMESTAMP(time_begin)/60 - UNIX_TIMESTAMP(CURDATE())/60 AS UNSIGNED) AS BeginShiftMinutes,
                duration_minutes AS DurationShiftMinutes
            FROM workshifts
            WHERE id=:shift_id', ['shift_id'=>$_REQUEST['shiftId']]);

        $schedule = query('SELECT
                ms.id, ms.begin_minutes, ms.duration_minutes AS duration, ms.comment, ms.s_type, s.name AS text
            FROM masters_schedule ms
            LEFT JOIN services s ON ms.service_id=s.id
            WHERE ms.shift_id=:shift_id', ['shift_id'=>$_REQUEST['shiftId']]);

        sleep(0.1);
        return ['actions' => [
            [
                'type' => 'UPDATE_SCHEDULE_SHIFTS',
                'value' => [
                    $_REQUEST['shiftId'] => ['schedule' => _gField($schedule, 'begin_minutes', true)] + (array)$ws[0]
                ],
            ]
        ]];
    }

    /*
     *	Добавление услуги в рассписание мастера.
     */
    private function _ScheduleAddService($salonId, $shiftId, $servId, $servType, $beginTime, $endTime=null, $comment=null) {

		$ws = current(query("SELECT date_begin, time_begin, duration_minutes, master_id FROM workshifts WHERE id=?", [$shiftId]));

		if ($endTime) { // если время окончания не указано, предпологается его определение по masters_services -> duration_default
			$duration_minutes = timeToMinutes($endTime) - timeToMinutes($beginTime);
		}
		else {
			$duration_minutes = $this->WorkshiftsRepository->getServiceDurationByMaster($servId, $ws->master_id);
		}

		$begin_minutes = timeToMinutes($beginTime) - timeToMinutes($ws->time_begin);
		$end_minutes = $begin_minutes + $duration_minutes;

		$workshift = $this->WorkshiftsRepository->loadShiftById($salonId, $shiftId);
		$unix_minutes = strtotime($ws->date_begin . ' ' . $ws->time_begin) / 60 + $begin_minutes;
		// проверка накладки занятого временного интервала
		if ($this->WorkshiftsRepository->testToShove($workshift['schedule'], $unix_minutes, $duration_minutes)) {

			$id = DB::connection('mysql2')->table('masters_schedule')->insertGetId([
				'shift_id' => $shiftId,
				'service_id' => $servId,
				'begin_minutes' => $begin_minutes,
				'duration_minutes' => $duration_minutes,
				'comment' => $comment,
				's_type' => $servType,
			]);
			return $id;
        }
    }

    function ScheduleAddService() {
        return [
            'id' => $this->_ScheduleAddService($_REQUEST['salonId'], $_REQUEST['shiftId'], $_REQUEST['serviceId'], 'own', $_REQUEST['beginTime'], $_REQUEST['endTime'], $_REQUEST['comment']),
            'redirect' => [
                'url' => 'schedule-get',
                'params' => ['shiftId' => $_REQUEST['shiftId']],
            ],
        ];
    }

    /*
     *	Возвращает все известные системе услуги, для выбора нужной при создани новой услуги салона.
     */
    function GetAllServicesDir() {

        $cats = query('
            SELECT s.id,s.name
            FROM services s
            WHERE s.parent_service IS NULL');

        $cats = _gField($cats, 'id', false);


        $servs = query('
            SELECT s.id, s.name, cats.id category_id, cats.name category_name
            FROM services s
            JOIN services cats ON s.parent_service=cats.id');
        //dump($servs);
        $servs = _gField($servs, 'id', false);

        /*
        foreach($servs as $serv) {
            $cats[$serv['parent_service']]['services'][$serv['id']] = $serv;
            unset($serv['parent_service']);
            unset($serv['id']);
        }
        */

        return compact('servs', 'cats');

    }

    function GetSalonServicesList(SalonAdmin $SalonAdmin) {
        // return ['request' => $_REQUEST, 'get' => $_GET, 'post' => $_POST];
        $p = (object) $_REQUEST;
        $this->authorize('master-of-salon', [$p->salonId, false]);

        return $SalonAdmin->_GetSalonServicesList((int) $p->salonId, isset($p->serviceId) ? $p->serviceId : null);
    }


    function CreateSalonService(SalonAdmin $SalonAdmin) {
        $p = (object) $_REQUEST;
        $this->authorize('master-of-salon', [$p->salonId, true]);

        // валидация

        if (empty($p->serviceId)) {
            $catId = (int) $p->catId;
            // проверить корректнось catId

            $serviceId = DB::connection('mysql2')->table('services')->insertGetId([
                'parent_service' => $catId,
                'name' => $p->serviceName,
                'adding_salon' => $p->salonId,
            ]);
        }
        else {
            $s = query('SELECT id, parent_service, name
                FROM services
                WHERE id=? AND parent_service IS NOT NULL AND (adding_salon IS NULL OR adding_salon=?)', [$p->serviceId, $p->salonId]);
            if (empty($s)) {
                // кинуть exception
            }
            list('id' => $serviceId, 'parent_service' => $catId, 'name' => $serviceName) = (array)$s[0];
        }

        if (query("SELECT id FROM salons_services WHERE salon_id=? and service_id=?", [$p->salonId, $serviceId])) {
            throw new \Exception("Услуга $serviceId уже имеется в салоне $p->salonId");
        }

        $salonServiceId = DB::connection('mysql2')->table('salons_services')->insertGetId([
            'service_id' => $serviceId,
            'salon_id' => $p->salonId,
        ]);
        $SalonAdmin->saveSalonService($p->salonId, $serviceId, ['price_default' => 100, 'duration_default' => 10], []);

        return [
            'salonServiceId' => $salonServiceId,
            'categoryId' => $catId,
            'servicesBranch' => $SalonAdmin->_GetSalonServicesList((int) $p->salonId , $salonServiceId),
        ];
    }


    function GetMySalonServicesActiveRequests() {
      $p = (object) $_REQUEST;
      $this->authorize('master-of-salon', [$p->salonId, true]);

      return $this->WorkshiftsRepository->GetMySalonServicesActiveRequests($p->salonId);
    }

    function SetMyResponse() {
		$p = (object) $_REQUEST;
		$this->authorize('master-of-salon', [$p->salonId, true]);

		$request = current(query("SELECT rs.id, rs.service_id, date(rs.desired_time), time(rs.desired_time) t, rs.status, s.name service_name
			FROM requests_to_salons rs
			JOIN services s ON rs.service_id=s.id
			WHERE rs.id=? AND rs.salon_id=? ", [$p->serviceRequestId, $p->salonId]));

		if ($request->status != 'proposed' ) {
			throw new \RangeException("Запрос: $p->serviceRequestId, в салон: $p->salonId - не актуален");
		}

		$newStatus = isset($p->shiftId) ? (
			$this->_ScheduleAddService($p->salonId, $p->shiftId, $request->service_id, 'external', $request->t) ? 'accepted' : 'conflicting'
		) : 'rejected';

		query("UPDATE requests_to_salons SET status=? WHERE id=?", [$newStatus, $request->id]);
		// TODO: Предпологается связь между запросом услуги и созданого рассписания. Ворос, кто на кого будет ссылаться в БД.
		$results = [
			'accepted' => ['icon' => 'ok', 'text' => "Услуга $request->service_name была добавлена в рассписание."],
			'conflicting' => ['icon' => 'warning', 'text' => 'Время уже занято'],
			'rejected' => ['icon' => 'info', 'text' =>'Отказ принят'],
		];

		return ['message' => [
				'title' => 'Что-то там произошло.',
				'text' => $results[$newStatus]['text'],
				'iconType' => $results[$newStatus]['icon'],
			]];
    }

    function setMemberOfSalon() {
        $p = (object) $_REQUEST;
        $this->authorize('master-of-salon', [$p->salonId, true]);

        if ($p->action === 'accept') {
            setSetField($p->memberId, ['ordinary'], 2, 'salon_masters', 'roles');
        }
        elseif ($p->action === 'reject') {
            setSetField($p->memberId, ['rejected'], 1, 'salon_masters', 'roles');
        }
    }

    function uploadImage(ImagesStore $ImagesStore) {
        //return ['get' => $_GET, 'post' => $_POST, 'files' => $_FILES];
        // TODO: нужна проверка прав на объект через салон. КАК???

        return $ImagesStore->saveImage($_REQUEST['objId'], $_REQUEST['objType'], $_FILES['image']);
    }

    function removeImage(ImagesStore $ImagesStore) {
        // TODO: нужна проверка прав на объект через салон. КАК???
        $ImagesStore->removeImage($_REQUEST['objId'], $_REQUEST['objType'], $_REQUEST['filename']);

    }

    function SaveSalonService(SalonAdmin $SalonAdmin) {
        $p = (object) $_REQUEST;
        //return $_REQUEST;
        //return $p->servMastersList;
        $this->authorize('master-of-salon', [$p->salonId, true]);

        $servData = [];
        foreach (['price_default'=>'servPrice', 'duration_default'=>'servDuration'] as $k => $d) {
            if (isset($p->$d)) {
                $servData[$k] = $p->$d;
            }
        }
        $servMastersList = isset($p->servMastersList) ? (array) $p->servMastersList : [];
        return $SalonAdmin->saveSalonService((int) $p->salonId, (int) $p->servId, $servData, $servMastersList);
    }

    /*
    function getMastersList(SalonAdmin $SalonAdmin) {
        $p = (object) $_REQUEST;
        $this->authorize('master-of-salon', [$p->salonId, false]);

        return $SalonAdmin->getMastersList($p->salonId);
    }
    */
}
