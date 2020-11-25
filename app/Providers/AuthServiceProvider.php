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
	    $f = query("SELECT flags FROM persons p
	      JOIN masters m ON p.id=m.person_id
	      WHERE m.salon_id=? AND p.user_id=? ", [$salonId, $user->id]);
	    //mylog($user->id);
	    //mylog(compact('salonId', 'adminRequired') );
	    //mylog($f);
	    //mylog(strpos($f[0]->flags, 'isAdmin'));
	    if ($f) {
	      return $adminRequired ? strpos($f[0]->flags, 'isAdmin') !== false : true;
	    }

            return false;
        });
        Gate::define('save-salon-service', function(User $user, $salonId) {
            return true;
        });
    }
}
