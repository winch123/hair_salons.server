<?php

namespace  App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Auth;
use Gate;

class SalonController extends Controller
{
    function ActualWorkshiftsGet() {
        $salonId = $_GET['salonId'];

        //if (! Gate::allows('is-master-of-salon', $salonId)) {
        //    return ['no access'];
        //}
        $this->authorize('master-of-salon', [$salonId, false]);

        $wss = query('SELECT  id shift_id, master_id, date_begin, time_begin, duration_minutes
            FROM workshifts
            WHERE salon_id=:salon_id', ['salon_id'=>$salonId]);
        $workshifts = $masters_ids = [];
        foreach ($wss as $ws) {
            extract((array)$ws);
            unset($ws->date_begin);
            unset($ws->master_id);
            $ws->text = "$ws->time_begin / $ws->duration_minutes";

            $workshifts[$date_begin]['masters'][$master_id] = $ws;
            //$workshifts[$date_begin]['masters'][$master_id] = $ws['text'];
            $workshifts[$date_begin]['caption'] = 'день: ' . $date_begin;
            $masters_ids[] = $master_id;
        }

        //$persons = query("SELECT id,name FROM persons WHERE id in (?)", [$masters_ids]);
        $persons = DB::connection('mysql2')->table('persons')->whereIn('id', $masters_ids)->get();
	$user = Auth::user();

        return compact('workshifts', 'persons', 'user');
    }

    function ScheduleGet() {
        $ws = query('SELECT
                CAST(UNIX_TIMESTAMP(time_begin)/60 - UNIX_TIMESTAMP(CURDATE())/60 AS UNSIGNED) AS BeginShiftMinutes,
                duration_minutes AS DurationShiftMinutes
            FROM workshifts
            WHERE id=:shift_id', ['shift_id'=>$_GET['shiftId']]);

        $schedule = query('SELECT
                ms.id, ms.begin_minutes, ms.duration_minutes AS duration, ms.comment, ms.s_type, s.name AS text
            FROM masters_schedule ms
            LEFT JOIN masters_services msr ON ms.master_service_id=msr.id
            LEFT JOIN services s ON msr.service_id=s.id
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

    function ScheduleAddService() {
        $workshift = query("SELECT time_begin,duration_minutes FROM workshifts WHERE id=?", [$_GET['shiftId']] );
        $begin_minutes = timeToMinutes($_GET['beginTime']) - timeToMinutes($workshift[0]->time_begin);
        $duration_minutes = timeToMinutes($_GET['endTime']) - timeToMinutes($_GET['beginTime']);

        $id = DB::connection('mysql2')->table('masters_schedule')->insertGetId([
            'shift_id' => $_GET['shiftId'],
            'master_service_id' => $_GET['masterServiceId'],
            'begin_minutes' => $begin_minutes,
            'duration_minutes' => $duration_minutes,
            'comment' => $_GET['comment'],
        ]);

        // TODO: проверять накладку занятого времени.

        return [
            'id' => $id,
            'redirect' => [
                'url' => '/salon/schedule-get',
                'params' => ['shiftId' => $_GET['shiftId']],
            ],
        ];
    }

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

    private function _GetSalonServicesList(int $catId=null): array
    {
      /*
        $sl = query('
            SELECT ms.service_id, ms.price_default, ms.duration_default, s.parent_service, s.name
            FROM masters_services ms
            JOIN services s ON s.id=ms.service_id
            WHERE ms.salon_id=:salon_id AND ms.person_id is null', ['salon_id'=>$_GET['salonId']]);
            */

        $sl = DB::connection('mysql2')->table('masters_services AS ms');
	$sl->select('ms.service_id', 'ms.price_default', 'ms.duration_default', 's.parent_service', 's.name')
	    ->join('services AS s', 's.id', '=', 'ms.service_id')
	    ->where('ms.salon_id','=', (int) $_GET['salonId'])
	    ->whereNull('ms.person_id')
	    ;
	if ($catId) {
	  $sl->where('s.parent_service', '=', $catId);
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
                    ', ['salon_id'=>$_GET['salonId']]);
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

    function GetSalonServicesList() {
      return $this->_GetSalonServicesList();
    }

    function SaveSalonService() {
	$p = (object) $_GET;
        // проверка прав: $user.person_id isAdmin in $_GET.salon_id ?
	$this->authorize('master-of-salon', [$p->salonId, true]);

        // валидация

        if (empty($p->serviceId)) {
	  $catId = $p->catId;
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
	    where person_id is null and salon_id=? and service_id=?", [$p->salonId, $p->serviceId]);
        if (!$isExistsMS) {
	  DB::connection('mysql2')->table('masters_services')->insert([
	    'service_id' => $serviceId,
	    'salon_id' => $p->salonId,
	  ]);
        }

        return $this->_GetSalonServicesList($catId);


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
}
