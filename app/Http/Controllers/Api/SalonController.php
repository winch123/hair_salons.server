<?php

namespace  App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Auth;
use Gate;

use App\winch\WorkshiftsRepository;

class SalonController extends Controller
{
    public function __construct(WorkshiftsRepository $WorkshiftsRepository)
    {
        $this->WorkshiftsRepository = $WorkshiftsRepository;
    }

    /*
     *	Возвращает существующие смены салона.
     */
    function ActualWorkshiftsGet() {
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
            GROUP BY ws.id', ['salon_id'=>$salonId]);
        $workshifts = $masters_ids = [];
        foreach ($wss as $ws) {
            extract((array)$ws);
            unset($ws->date_begin);
            unset($ws->master_id);
            $ws->text = strftime('%H:%M', strtotime($ws->time_begin)) . ' - ' . strftime('%H:%M', strtotime("+$ws->duration_minutes min", strtotime($ws->time_begin)));
	    $ws->description = '<div>услуг: <b>' . $ws->total_services_count . '</b>, свободно: <b>' . ($ws->duration_minutes - $ws->busy_duration) . '</b> мин</div>';
            $workshifts[$date_begin]['masters'][$master_id] = $ws;
            $workshifts[$date_begin]['caption'] = strftime('%d %b - %a', strtotime($date_begin));
            $masters_ids[] = $master_id;
        }

        //$persons = query("SELECT id,name FROM persons WHERE id in (?)", [$masters_ids]);
        $persons = _gField(DB::connection('mysql2')->table('persons')->whereIn('id', $masters_ids)->get(), 'id', false);
	$user = Auth::user();

        return compact('workshifts', 'persons', 'user');
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
            WHERE id=:shift_id', ['shift_id'=>$_GET['shiftId']]);

        $schedule = query('SELECT
                ms.id, ms.begin_minutes, ms.duration_minutes AS duration, ms.comment, ms.s_type, s.name AS text
            FROM masters_schedule ms
            LEFT JOIN services s ON ms.service_id=s.id
            WHERE ms.shift_id=:shift_id', ['shift_id'=>$_GET['shiftId']]);

        sleep(0.1);
        return ['actions' => [
            [
                'type' => 'UPDATE_SCHEDULE_SHIFTS',
                'value' => [
                    $_GET['shiftId'] => ['schedule' => _gField($schedule, 'begin_minutes', true)] + (array)$ws[0]
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

		$workshifts = $this->WorkshiftsRepository->loadShifts($salonId, null, $shiftId);
		$unix_minutes = strtotime($ws->date_begin . ' ' . $ws->time_begin) / 60 + $begin_minutes;
		// проверка накладки занятого временного интервала
		//var_dump([ $workshifts[$shiftId]['schedule'], $unix_minutes, $duration_minutes ]);
		if ($this->WorkshiftsRepository->testToShove($workshifts[$shiftId]['schedule'], $unix_minutes, $duration_minutes)) {

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
            'id' => $this->_ScheduleAddService($_GET['salonId'], $_GET['shiftId'], $_GET['serviceId'], 'own', $_GET['beginTime'], $_GET['endTime'], $_GET['comment']),
            'redirect' => [
                'url' => '/schedule-get',
                'params' => ['shiftId' => $_GET['shiftId']],
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

    /*
     * 	Возвращает услуги салона в виде иерахии, начиная с категорий.
     */
    private function _GetSalonServicesList(int $salonId, int $catId=null, int $masrerId=null): array
    {
      /*
        $sl = query('
            SELECT ms.service_id, ms.price_default, ms.duration_default, s.parent_service, s.name
            FROM masters_services ms
            JOIN services s ON s.id=ms.service_id
            WHERE ms.salon_id=:salon_id AND ms.person_id is null', ['salon_id'=>$salonId]);
            */

        $sl = DB::connection('mysql2')->table('masters_services AS ms');
	$sl->select('ms.service_id', 'ms.price_default', 'ms.duration_default', 's.parent_service', 's.name')
	    ->join('services AS s', 's.id', '=', 'ms.service_id')
	    ->where('ms.salon_id','=', $salonId)
	    ->whereNull('ms.person_id');

	if ($catId) {
	  $sl->where('s.parent_service', '=', $catId);
	}

	if ($masrerId) {
	  $sl->join('masters_services AS master', 'master.service_id', '=', 'ms.service_id')
	    ->where('master.person_id', '=', $masrerId);
	}
	//var_dump($sl->toSql());
	//$sl->dd();

        $sl = _gField($sl->get(), 'service_id');
        //dump($sl);
        //dump( array_unique(array_column($sl, 'parent_service')) ); exit;

	$cats = DB::connection('mysql2')
	    ->table('services')
	    ->select('id','name')
	    ->whereIn('id', array_unique(array_column($sl, 'parent_service')));
        $cats = _gField($cats->get(), 'id');

        $masters = query('
            SELECT ms.person_id, ms.service_id, p.name
            FROM masters_services ms
            JOIN persons p ON p.id=ms.person_id
            WHERE ms.salon_id=:salon_id AND ms.person_id is not null
                    ', ['salon_id'=>$salonId]);
        foreach ($masters as &$m) {
	  if (isset($sl[$m->service_id])) {
            $sl[$m->service_id]['masters'][$m->person_id] = &$m;
            unset($m->service_id);
            unset($m->person_id);
	  }
        }

        //dump($sl);
        foreach ($sl as $k=>$s) {
	  //var_dump($s); echo '---';
            $cats[$s['parent_service']]['services'][$k] = $s;
        }

        return $cats;
    }

    /*
     *
     */
    private function _AddMastersToService(int $salonId, int $serviceId, array $masters=[])
    {
      // Предполагается указание списка матеров, при этом  нужна проверка, что все указанные мастера действительно работают в этом салоне.
      $masters = query("SELECT person_id FROM masters WHERE salon_id=?", [$salonId]);

      foreach ($masters as $master) {
	  DB::connection('mysql2')->table('masters_services')->insert([
	    'service_id' => $serviceId,
	    'salon_id' => $salonId,
	    'person_id' => $master->person_id,
	  ]);
      }
    }

    function GetSalonServicesList() {
      return $this->_GetSalonServicesList((int) $_GET['salonId']);
    }

    function SaveSalonService() {
	$p = (object) $_GET;
        // проверка прав: $user.person_id isAdmin in $_GET.salon_id ?
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
	  //dump((array) $s[0]);
	  list('id' => $serviceId, 'parent_service' => $catId, 'name' => $serviceName) = (array)$s[0];
	}
	//return [$serviceId, $catId, $serviceName];

        $isExistsMS = query("select id
	    from masters_services
	    where person_id is null and salon_id=? and service_id=?", [$p->salonId, $serviceId]);
        if (!$isExistsMS) {
	  DB::connection('mysql2')->table('masters_services')->insert([
	    'service_id' => $serviceId,
	    'salon_id' => $p->salonId,
	    'price_default' => 100,
	    'duration_default' => 20,
	  ]);
	  $this->_AddMastersToService($p->salonId, $serviceId);
        }

        return [
	    'serviceId' => $serviceId,
	    'categoryId' => $catId,
	    'servicesBaranch' => $this->_GetSalonServicesList((int) $p->salonId , $catId),
	];


	///////////////////////
        // update masters_services set price_default,duration_default
        //     where person_id is null and salon_id=? and service_id=?

        foreach($_GET['addMasters'] as $master_id) {
            // replase masters_services (person_id, service_id, salon_id, price_default,duration_default) vlalues ($master_id....)
        }
        foreach($_GET['deleteMasters'] as $master_id) {
            // delete from masters_services where person_id=$master_id and salon_id=? and service_id=?
        }
    }

    function GetMySalonServicesActiveRequests() {
      $p = (object) $_REQUEST;
      $this->authorize('master-of-salon', [$p->salonId, true]);

      return $this->WorkshiftsRepository->GetMySalonServicesActiveRequests($p->salonId);
    }

    function SetMyResponse() {
      $p = (object) $_REQUEST;
      $this->authorize('master-of-salon', [$p->salonId, true]);

		$request = current(query("SELECT id, service_id, date(desired_time), time(desired_time) t
			FROM requests_to_salons
			WHERE status='proposed' AND id=? AND salon_id=? ", [$p->serviceRequestId, $p->salonId]));

		$newStatus = isset($p->shiftId) ? (
			$this->_ScheduleAddService($p->salonId, $p->shiftId, $request->service_id, 'external', $request->t) ? 'accepted' : 'conflicting'
		) : 'rejected';

		query("UPDATE requests_to_salons SET status=? WHERE id=?", [$newStatus, $request->id]);
// 		// TODO: Предпологается связь между запросом услуги и созданого рассписания. Ворос кто на кого будет ссылаться.

		return ['message' => $newStatus];
    }

}
