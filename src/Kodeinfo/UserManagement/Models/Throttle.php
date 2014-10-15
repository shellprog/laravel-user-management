<?php namespace KodeInfo\UserManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Config;

class Throttle extends Model {

    public $table = "throttle";

    function __construct(){
        $this->table = Config::get("user-management::throttle_table");
        parent::__construct();
    }

}