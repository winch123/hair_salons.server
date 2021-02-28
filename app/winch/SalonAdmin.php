<?php
namespace App\winch;

use DB;
use Illuminate\Support\Facades\Auth;

class SalonAdmin
{
    function addMeToSalon($salonExternalId) {
        $person = current(query("SELECT id FROM hs.persons WHERE user_id=?", [Auth::user()->id]));

        $s = query("SELECT s.id, sm.person_id
            FROM hs.salons s
            LEFT JOIN hs.salon_masters sm ON s.id=sm.salon_id AND sm.person_id=?
            WHERE s.external_id=? ", [$person->id, $salonExternalId]);

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

        query("INSERT INTO hs.salon_masters (salon_id, person_id, roles) VALUES (?,?,?) ", [$salonId, $person->id ,$roles]);
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

    function loadPerson($id) {
        return current(query("SELECT id,name FROM hs.persons WHERE id=?", [$id]));
    }
}
