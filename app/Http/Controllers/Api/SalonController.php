<?php

namespace  App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
use DB;

class SalonController extends Controller
{
    function ActualWorkshiftsGet() {

        $wss = query('SELECT  id shift_id, master_id, date_begin, time_begin, duration_minutes
            FROM workshifts
            WHERE salon_id=:salon_id', ['salon_id'=>2]);
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

        return compact('workshifts', 'persons');
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

    function GetSalonServicesList() {
        $cats = query('
            SELECT id, name FROM services WHERE parent_service IS NULL');
        $cats = _gField($cats, 'id');

        $sl = query('
            SELECT ms.service_id, ms.price_default, ms.duration_default, s.parent_service, s.name
            FROM masters_services ms
            JOIN services s ON s.id=ms.service_id
            WHERE ms.salon_id=:salon_id AND ms.person_id is null
                    ', ['salon_id'=>$_GET['salonId']]);
        $sl = _gField($sl, 'service_id');
        //var_dump($sl); exit;

        $masters = query('
            SELECT ms.person_id, ms.service_id, p.name
            FROM masters_services ms
            JOIN persons p ON p.id=ms.person_id
            WHERE ms.salon_id=:salon_id AND ms.person_id is not null
                    ', ['salon_id'=>$_GET['salonId']]);
        foreach ($masters as &$m) {
            $sl[$m->service_id]['masters'][$m->person_id] = &$m;
            unset($m->service_id);
            unset($m->person_id);
        }
        foreach ($sl as $k=>$s) {
            $cats[$s['parent_service']]['services'][$k] = $s;
        }

        return $cats;
    }

    function GetAllServicesDir() {
        /*
        $cats = Yii::$app->db->createCommand('
            SELECT s.id,s.name
            FROM services s
            WHERE s.parent_service IS NULL')->queryAll();

        $cats = _gField($cats, 'id');
        */

        $servs = query('
            SELECT s.id, s.name,cats.name category_name
            FROM services s
            JOIN services cats ON s.parent_service=cats.id');
        //dump($servs);
        $servs = _gField($servs, 'id');

        /*
        foreach($servs as $serv) {
            $cats[$serv['parent_service']]['services'][$serv['id']] = $serv;
            unset($serv['parent_service']);
            unset($serv['id']);
        }
        */

        return $servs;

    }
}
