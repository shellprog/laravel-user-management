<?php namespace KodeInfo\UserManagement\Models;

use Eloquent;
use Config;

class Throttle extends Eloquent {

    public $table = "throttle";

    function __construct(){
        $this->table = Config::get("user-management::throttle_table");
        parent::__construct();
    }

}