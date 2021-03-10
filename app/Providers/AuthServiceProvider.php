<?php

namespace App\Providers;

use Laravel\Passport\Passport;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
        Passport::routes();

        Gate::define('master-of-salon', function($user, $salonId, $adminRequired) {
            $f = query("SELECT sm.roles
                FROM salon_masters sm
                WHERE sm.salon_id=? AND sm.person_id=? ", [$salonId, $user->person_id]);
            //mylog($user->id);
            //mylog(compact('salonId', 'adminRequired') );
            //mylog($f);
            //var_dump($f); exit;
            if ($f) {
                $roles = strToAssoc($f[0]->roles);
                if ($adminRequired) {
                    return isset($roles->admin);
                }
                else {
                    return isset($roles->ordinary) || isset($roles->admin);
                }
            }

            return false;
        });

        Gate::define('save-salon-service', function(User $user, $salonId) {
            return true;
        });
    }
}
