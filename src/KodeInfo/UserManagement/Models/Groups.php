<?php namespace KodeInfo\UserManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Config;

class Groups extends Model {

    public $table = "groups";

    function __construct(){
        $this->table = Config::get("user-management::groups_table");
        parent::__construct();
    }

}