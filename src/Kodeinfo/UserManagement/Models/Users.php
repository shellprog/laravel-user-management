<?php namespace KodeInfo\UserManagement\Models;

use Eloquent;
use Config;
use DB;
use Crypt;

class Users extends Eloquent {

    public $table = "users";

    function __construct(){
        $this->table = Config::get("user-management::users_table");
        parent::__construct();
    }

    public function addGroup($group)
    {
        $users_group = Config::get("user-management::users_groups_table");

        if (DB::table($users_group)->where("user_id", $this->id)->where("group_id", $group->id)->count() <= 0) {
            //Insert
            DB::table($users_group)->insert(array("user_id" => $this->id, "group_id" => $group->id));
        }
    }

    public function attemptActivation($code)
    {
        //Match activation code with $user->activation_code
        if ($this->checkActivationCode($code)) {
            $this->activation_code = null;
            $this->activated = 1;
            $this->activated_at = date('Y-m-d H:i:s');

            $this->save();

            return true;
        } else {
            return false;
        }

    }

    public function checkActivationCode($activation_code)
    {

        if ($this->doExists(["email" => $this->email, "activation_code" => $activation_code]))
            return true;
        else
            return false;

    }

    public function getActivationCode()
    {

        if (strlen($this->activation_code) > 0) {
            return $this->activation_code;
        }

        $this->activation_code = $this->generateActivationCode();

        $this->save();

        return $this->activation_code;
    }

    public function generateActivationCode()
    {

        $code = Crypt::encrypt(str_random(12));

        if ($this->doExists(["activation_code" => $code])) {
            $this->generateActivationCode();
        }

        return $code;
    }

    public function doExists(array $conditions)
    {

        $table = DB::table($this->table);

        foreach ($conditions as $key => $value) {
            $table->where($key, $value);
        }

        return (boolean)$table->count() > 0 ? true : false;

    }


} 