<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    use HasFactory;

    protected $connection = 'mysql2';
    protected $table = 'persons';
    protected $fillable = ['user_id','name'];
    public $timestamps = false;


    /*
    public function create($userId) {
        $p = new self;
        $p->user_id = $userId;
        $p->name = 'new person';
        $p->save();
        return $p;
    }
    */

}
