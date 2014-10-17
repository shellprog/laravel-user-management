<?php namespace KodeInfo\UserManagement\Models;

use Config;
use DB;
use Crypt;
use Auth;
use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;

class Users extends \Eloquent implements UserInterface, RemindableInterface{

    use UserTrait, RemindableTrait;

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

    public function allGroups()
    {
        $users_groups_table = Config::get("user-management::users_groups_table");
        $groups = Config::get("user-management::groups_table");

        $group_ids = DB::table($users_groups_table)->where("user_id",$this->id)->select('*')->get();

        return DB::table($groups)->whereIn("id",$group_ids)->select('*')->get();
    }

    public function isAdmin()
    {
       return $this->inGroup('admin');
    }

    public function isGuest()
    {
        return $this->inGroup('guest');
    }

    public function isCustomer()
    {
        return $this->inGroup('customer');
    }

    public function isSuperAdmin()
    {
        return $this->inGroup('super_admin');
    }

    public function hasPermission($permission_name)
    {
        $users_groups_table = Config::get("user-management::users_groups_table");
        $groups = Config::get("user-management::groups_table");

        $group_ids = DB::table($users_groups_table)->where("user_id",$this->id)->select('*')->get();

        $groups = DB::table($groups)->whereIn("id",$group_ids)->lists('permissions');

        foreach($groups as $group){

            $permissions = json_decode($group->permissions);

            if(in_array($permission_name,$permissions)){
                return true;
            }
        }

        return false;
    }

    public function inGroup($group_name_or_id)
    {
        $users_groups_table = Config::get("user-management::users_groups_table");
        $groups_table = Config::get("user-management::groups_table");

        if(is_integer($group_name_or_id)){
            $groups = DB::table($users_groups_table)->where("user_id",$this->id)->where("group_id",$group_name_or_id)->get();
        }else{
            $group = DB::table($groups_table)->where("name",$group_name_or_id)->get();
            $groups = DB::table($users_groups_table)->where("user_id",$this->id)->where("group_id",$group->id)->get();
        }

       if(sizeof($groups)>0){
           return true;
       }

        return false;
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