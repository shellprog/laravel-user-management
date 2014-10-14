<?php namespace Kodeinfo\UserManagement;

use Config;
use DB;
use App;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Schema;
use Hash;
use Str;
use Crypt;
use Session;
use Auth;
use Lang;
use KodeInfo\UserManagement\Exceptions as Exceptions;
use KodeInfo\UserManagement\Models\Users as UsersModel;
use KodeInfo\UserManagement\Models\Groups as GroupsModel;
use KodeInfo\UserManagement\Models\Throttle as ThrottleModel;

class UserManagement
{

    public $users_table;
    public $users_groups_table;
    public $groups_table;
    public $throttle_table;
    public $suspended_interval;

    function  __construct($arr = null)
    {
        $this->users_table = Config::get("user-management::users_table");
        $this->users_groups_table = Config::get("user-management::users_groups_table");
        $this->groups = Config::get("user-management::groups_table");
        $this->throttle = Config::get("user-management::throttle_table");
        $this->suspended_interval = Config::get("user-management::suspended_interval");

        if (!is_null($arr)) {
            $item = $this->initialize($arr);
            return $item;
        }
    }

    public function initialize($arr)
    {

        $user = null;

        if (is_numeric($arr)) {
            $user = $this->findUserById($arr);
        } else {
            $user = $this->findUserByLogin($arr);
        }

        return $user;
    }

    public function createModel()
    {
        return new UsersModel();
    }

    public function createUser($inputs, $group = null, $activate = false)
    {
        //Email and password is required other fields will be checked using hasColumn and filled

        if (!isset($inputs['email']) || !isset($inputs['password'])) {
            //Email and Password is always required to create an account
            throw new Exceptions\LoginFieldsMissingException(Lang::get('messages.email_password_missing'), array(Lang::get('messages.email_password_missing')));
        }

        if ($inputs['password'] !== $inputs['password_confirmation'])
        {
            throw new Exceptions\AuthException(Lang::get('messages.passwords_do_not_match'),array(Lang::get('messages.passwords_do_not_match')));
        }

        if ($this->doExists(["email" => $inputs['email']])) {
            //Email Already Exists
            throw new Exceptions\UserAlreadyExistsException(Lang::get('messages.email_already_exists'), array(Lang::get('messages.email_already_exists')));
        }

        if ($this->doExists(["username" => $inputs['username']])) {
            //Username Already Exists
            throw new Exceptions\UserAlreadyExistsException(Lang::get('messages.username_already_exists'), array(Lang::get('messages.username_already_exists')));
        }

        $user = $this->createModel();

        foreach ($inputs as $key => $value) {

            if (!Schema::hasColumn($this->users_table, $key)) {
                throw new Exceptions\ColumnNotFoundException(Lang::get("messages.column_not_found", ['key' => $key]), array(Lang::get("messages.column_not_found", ['key' => $key])));
            }

            if ($key == "password") {
                $value = Hash::make($value);
            }

            $user->{$key} = $value;

        }

        $user->save();

        if (!is_null($group)) {

            if (is_integer($group)) {
                $grp = $this->findGroupById($group);
            } else {
                $grp = $this->findGroupByName($group);
            }

            $user->addGroup($grp);
        }

        if ($activate) {
            $user->attemptActivation($user->getActivationCode());
        }

        return $user;


    }

    public function findUserByLogin($email)
    {

        if ($this->doExists(['email' => $email])) {
            $model = $this->createModel();
            $user = $model->newQuery()->where("email", '=', $email)->first();
            return $user;
        } else {
            throw new Exceptions\UserNotFoundException(Lang::get('messages.user_not_found'), array(Lang::get('messages.user_not_found')));
        }

    }

    public function findUserById($id)
    {
        if ($this->doExists(['id' => $id])) {
            $model = $this->createModel();
            $user = $model->newQuery()->find($id);
            return $user;
        } else {
            throw new Exceptions\UserNotFoundException(Lang::get('messages.user_not_found'), array(Lang::get('messages.user_not_found')));
        }
    }

    public function createGroup($name = null)
    {

        if (is_null($name) || strlen($name) <= 0) {
            throw new Exceptions\NameRequiredException(Lang::get('messages.group_name_required'), array(Lang::get('messages.group_name_required')));
        }

        if (GroupsModel::where('name', $name)->count() > 0) {
            throw new Exceptions\GroupExistsException(Lang::get('messages.group_already_required'), array(Lang::get('messages.group_already_required')));
        } else {
            $group = new GroupsModel();
            $group->name = $name;
            $group->save();

            return $group;
        }
    }

    public function allGroups()
    {
        return DB::table($this->groups_table)->select('*')->get();
    }

    public function deleteGroup($arr)
    {

        try {

            if (is_numeric($arr)) {
                $group = GroupsModel::findOrFail($arr);
            } else {
                $group = GroupsModel::where("name", Str::lower($arr));
            }

            $group->delete();

        } catch (ModelNotFoundException $e) {
            throw new Exceptions\GroupNotFoundException(Lang::get('messages.group_not_found'), [Lang::get('messages.group_not_found')]);
        }

    }

    public function findGroupById($group_id)
    {
        try {
            return GroupsModel::find($group_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new Exceptions\GroupNotFoundException(Lang::get('messages.group_not_found'), array(Lang::get('messages.group_not_found')));
        }

    }

    public function findGroupByName($group_name)
    {
        if (DB::table($this->groups_table)->where("name", $group_name)->count() > 0) {
            return DB::table($this->groups_table)->where("name", $group_name)->first();
        } else {
            throw new Exceptions\GroupNotFoundException(Lang::get('messages.group_not_found'), array(Lang::get('messages.group_not_found')));
        }
    }

    public function doExists(array $conditions)
    {

        $table = DB::table($this->users_table);

        foreach ($conditions as $key => $value) {
            $table->where($key, $value);
        }

        return (boolean)$table->count() > 0 ? true : false;

    }

    public function findThrottlerByUserId($id)
    {

        if (DB::table($this->throttle_table)->where("user_id", $id)->count() > 0) {
            //Throttle exists
            return ThrottleModel::where("user_id", $id)->first();
        } else {

            //Create new throttle
            $throttle = new ThrottleModel();
            $throttle->user_id = $id;
            $throttle->ip_address = getIpAddress();
            $throttle->attempts = 0;
            $throttle->suspended = 0;
            $throttle->banned = 0;
            $throttle->last_attempt_at = null;
            $throttle->suspended_at = null;
            $throttle->banned_at = null;
            $throttle->save();

            return $throttle;
        }
    }

    public function generateResetCode()
    {

        $code = Crypt::encrypt(str_random(12));

        if (DB::table($this->users_table)->where("reset_password_code", $code)->count() > 0) {
            $this->generateResetCode();
        }

        return $code;
    }

    public function login($credentials, $remember, $check_throttle = true)
    {

        if ($check_throttle) {

            $user_available = UsersModel::where("email", $credentials['email'])->count();

            if ($user_available == 0) {
                throw new Exceptions\UserNotFoundException(Lang::get('messages.account_not_found'), [Lang::get('messages.account_not_found')]);
            }

            $user = UsersModel::where("email", $credentials['email'])->first();

            //Check banned .
            if (ThrottleModel::where("user_id", $user->id)->where("banned", 1)->count() > 0) {
                throw new Exceptions\UserBannedException(Lang::get('messages.user_banned'), [Lang::get('messages.user_banned')]);
            }

            //Check suspended .
            if (ThrottleModel::where("user_id", $user->id)->where("suspended", "1")->count() > 0) {
                throw new Exceptions\UserSuspendedException(Lang::get('messages.user_suspended'), [Lang::get('messages.user_suspended')]);
            }
        }

        return Auth::attempt($credentials, $remember);
    }

    public function logout()
    {
        Session::flush();
        Auth::logout();
    }
} 