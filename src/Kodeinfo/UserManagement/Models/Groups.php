<?php namespace KodeInfo\UserManagement\Models;

use Eloquent;
use Config;

class Groups extends Eloquent {

    public $table = "groups";

    function __construct(){
        $this->table = Config::get("user-management::groups_table");
        parent::__construct();
    }

}