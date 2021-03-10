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
            $ext = query("SELECT name FROM yandex_maps_business.firms WHERE id=?", [$salonExternalId]);
            $salonId = query("INSERT INTO hs.salons (name, external_id) VALUES (?,?)", [$ext[0]->name, $salonExternalId]);
            $roles = 'admin';
        }
        else {
            if ($s[0]->person_id) { // уже имеем этот салон
                return false;
            }
            $salonId = $s[0]->id;
            $roles = null;
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

        return ['salons' => $salons];
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


    function setRoles($salonId, $personId, $roleName, $action) {
        /*
        добавить админа
         update `salon_masters` set roles=CONCAT(roles, ',admin') WHERE id=3

        удалить админа
         update `salon_masters` set roles=TRIM(BOTH ',' FROM REPLACE(CONCAT(',', roles, ','), ',admin,', ',')) WHERE id=3
        */

    }
}
