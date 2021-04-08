<?php
namespace App\winch;

use DB;
use Illuminate\Support\Facades\Auth;

class SalonAdmin
{
    function addMeToSalon($salonExternalId) {
        //$person = current(query("SELECT id FROM hs.persons WHERE user_id=?", [Auth::user()->id]));

        $s = query("SELECT s.id, sm.person_id
            FROM hs.salons s
            LEFT JOIN hs.salon_masters sm ON s.id=sm.salon_id AND sm.person_id=?
            WHERE s.external_id=? ", [Auth::user()->person_id, $salonExternalId]);

        if (empty($s)) {
            $ext = query("SELECT name FROM {firms} WHERE id=?", [$salonExternalId]);
            $salonId = query("INSERT INTO hs.salons (name, external_id) VALUES (?,?)", [$ext[0]->name, $salonExternalId]);
            $roles = 'ordinary,admin';
        }
        else {
            if ($s[0]->person_id) { // уже имеем этот салон
                return false;
            }
            $salonId = $s[0]->id;
            $roles = 'requested';
        }

        query("INSERT INTO hs.salon_masters (salon_id, person_id, roles) VALUES (?,?,?) ", [$salonId, Auth::user()->person_id, $roles]);
        return true;
    }

    function getMySalons() {
        $salons = query("SELECT s.id, s.name, sm.roles AS myRoles
            FROM hs.salons s
            JOIN hs.salon_masters sm ON s.id=sm.salon_id
            WHERE sm.person_id=?", [Auth::user()->person_id]);

        foreach($salons as &$salon) {
            $salon->myRoles = array_diff(explode(',',  $salon->myRoles), ['']);
        }

        return ['salons' => _gField($salons, 'id', false)];
    }

    function loadPerson($personId) {
        return current(query("SELECT id,name FROM hs.persons WHERE id=?", [$personId]));
    }

    function getMastersList($salonId) {
        $l = query("SELECT p.id, p.name, sm.roles, sm.id memberId
                FROM persons p
                JOIN salon_masters sm ON p.id=sm.person_id
                WHERE salon_id=?", [$salonId]);
        foreach($l as &$l0) {
            $l0->roles = strToAssoc($l0->roles);
        }

        return _gField($l, 'id', false);
    }

    /*
     * 	Возвращает услуги салона в виде иерахии, начиная с категорий.
     */
    function _GetSalonServicesList(int $salonId, int $serviceId=null): array
    {
        $sl = DB::connection('mysql2')->table('salons_services AS ss')
            ->select('ss.id', 'ss.service_id', 'ss.price_default', 'ss.duration_default', 's.parent_service', 's.name')
            ->join('services AS s', 's.id', '=', 'ss.service_id')
            ->where('ss.salon_id','=', $salonId);

        if ($serviceId) {
            $sl->where('ss.id', '=', $serviceId);
        }
        //var_dump($sl->toSql());
        //$sl->dd();
        $sl = _gField($sl->get(), 'id', false);
        //mylog($sl);

        $masters = query('
            SELECT ms.person_id, ss.service_id, ss.id ss_id
            FROM masters_services ms
            JOIN salons_services ss ON ms.salon_service_id=ss.id
            WHERE ss.salon_id=:salon_id', ['salon_id'=>$salonId]);
        foreach ($masters as &$master) {
            //mylog([$master, isset($sl[$master->id])]);
            if (isset($sl[$master->ss_id])) {
                //mylog('добавляем');
                $sl[$master->ss_id]['masters'][$master->person_id] = true;
                //unset($master->service_id);
                //unset($master->person_id);
            }
        }
        //mylog($sl);

        $imagesTree = (new ImagesStore)->getImagesOfObjects(array_keys($sl), 'masters_services');
        foreach($imagesTree as $k => $imgs) {
            $sl[$k]['images'] = $imgs;
        }

        // расфасовка по категориям
        $cats = DB::connection('mysql2')
            ->table('services')
            ->select('id','name')
            ->whereIn('id', array_unique(array_column($sl, 'parent_service')));
        $cats = _gField($cats->get(), 'id', false);
        foreach ($sl as $k => $s) {
            $cats[$s['parent_service']]['services'][$k] = $s;
        }
        return $cats;
    }

    function saveSalonService(int $salonId, int $servId, array $servData, array $mastersList) {
        $ssId = current(query("SELECT id FROM salons_services WHERE salon_id=? AND service_id=?", [$salonId, $servId]))->id;
        //return $mastersList;
        if ($servData) {
            DB::connection('mysql2')->table('salons_services')->where('id', $ssId)->update($servData);
        }

        //TODO: возможно нужна проверка, что все указанные мастера действительно работают в этом салоне.
        foreach($mastersList as $master) {
            query("INSERT INTO masters_services (salon_service_id, person_id) VALUES (?,?)
                    ON DUPLICATE KEY UPDATE price_default=null",
                [$ssId, $master]);
        }

        $sql = "DELETE FROM masters_services WHERE salon_service_id=? ";
        if (!empty($mastersList)) {
            $sql .= 'AND person_id NOT IN (ph0)';
        }
        query($sql, [$ssId, $mastersList]);
    }

    function setRoles($salonId, $personId, $roleName, $action) {
        /*
        добавить админа
         update `salon_masters` set roles=CONCAT(roles, ',admin') WHERE id=3

        удалить админа
         update `salon_masters` set roles=TRIM(BOTH ',' FROM REPLACE(CONCAT(',', roles, ','), ',admin,', ',')) WHERE id=3
        */
    }

}
