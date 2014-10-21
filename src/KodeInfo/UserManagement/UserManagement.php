<?php namespace KodeInfo\UserManagement;

use Config;
use DB;
use App;
use Schema;
use Hash;
use Str;
use Crypt;
use Session;
use Auth;
use Lang;
use KodeInfo\UserManagement\Exceptions\AuthException;
use KodeInfo\UserManagement\Exceptions\NameRequiredException;
use KodeInfo\UserManagement\Exceptions\ValidationException;
use KodeInfo\UserManagement\Exceptions\GroupExistsException;
use KodeInfo\UserManagement\Exceptions\GroupNotFoundException;
use KodeInfo\UserManagement\Exceptions\UserNotFoundException;
use KodeInfo\UserManagement\Exceptions\UserIdentityRequiredException;
use KodeInfo\UserManagement\Exceptions\UserAlreadyExistsException;
use KodeInfo\UserManagement\Exceptions\UserNotActivatedException;
use KodeInfo\UserManagement\Exceptions\ColumnNotFoundException;
use KodeInfo\UserManagement\Exceptions\UserBannedException;
use KodeInfo\UserManagement\Exceptions\UserSuspendedException;
use KodeInfo\UserManagement\Exceptions\LoginFailedException;
use KodeInfo\UserManagement\Exceptions\LoginFieldsMissingException;
use KodeInfo\UserManagement\Exceptions\LoginNotFoundException;
use KodeInfo\UserManagement\Exceptions\LoginValidatorException;
use KodeInfo\UserManagement\Models\Users;
use KodeInfo\UserManagement\Models\Groups;
use KodeInfo\UserManagement\Models\Throttle;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class UserManagement
 * @package KodeInfo\UserManagement
 */
class UserManagement
{

    /**
     * @var mixed
     */
    public $users_table;
    public $user;
    /**
     * @var mixed
     */
    public $users_groups_table;
    /**
     * @var
     */
    public $groups_table;
    /**
     * @var
     */
    public $throttle_table;
    /**
     * @var mixed
     */
    public $suspended_interval;

    /**
     * @param null $arr
     */
    function  __construct($arr = null)
    {
        $this->users_table = Config::get("user-management::users_table");
        $this->users_groups_table = Config::get("user-management::users_groups_table");
        $this->groups_table = Config::get("user-management::groups_table");
        $this->throttle_table = Config::get("user-management::throttle_table");
        $this->suspended_interval = Config::get("user-management::suspended_interval");

        if (!is_null($arr)) {
            $item = $this->initialize($arr);
            $this->user = $item;
        }
    }

    /**
     * @param $arr
     * @return null|void|Users
     * @throws UserNotFoundException
     */
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

    /**
     * @return Users
     */
    public function createModel()
    {
        return new Users();
    }

    /**
     * @param $inputs
     * @param null $group
     * @param bool $activate
     * @return Users
     * @throws AuthException
     * @throws GroupNotFoundException
     * @throws LoginFieldsMissingException
     * @throws UserAlreadyExistsException
     */
    public function createUser($inputs, $group = null, $activate = false)
    {

        if (strlen($inputs['email'])<=0 || strlen($inputs['password'])<=0) {
            //Email and Password is always required to create an account
            throw new LoginFieldsMissingException(trans('user-management::messages.email_password_missing'), array(trans('user-management::messages.email_password_missing')));
        }

        if ($inputs['password'] !== $inputs['password_confirmation'])
        {
            throw new AuthException(trans('user-management::messages.passwords_do_not_match'),array(trans('user-management::messages.passwords_do_not_match')));
        }

        if ($this->doExists(["email" => $inputs['email']])) {
            //Email Already Exists
            throw new UserAlreadyExistsException(trans('user-management::messages.email_already_exists'), array(trans('user-management::messages.email_already_exists')));
        }

        if (isset($inputs['username'])&&$this->doExists(["username" => $inputs['username']])) {
            //Username Already Exists
            throw new UserAlreadyExistsException(trans('user-management::messages.username_already_exists'), array(trans('user-management::messages.username_already_exists')));
        }

        $user = $this->createModel();

        foreach ($inputs as $key => $value) {

            if (!Schema::hasColumn($this->users_table, $key)) {
                continue;
                //throw new ColumnNotFoundException(trans("messages.column_not_found", ['key' => $key]), array(trans("messages.column_not_found", ['key' => $key])));
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

    /**
     * @param $email
     * @throws UserNotFoundException
     */
    public function findUserByLogin($email)
    {

        if ($this->doExists(['email' => $email])) {
            $model = $this->createModel();
            $user = $model->newQuery()->where("email", '=', $email)->first();
            return $user;
        } else {
            throw new UserNotFoundException(trans('user-management::messages.user_not_found'), array(trans('user-management::messages.user_not_found')));
        }

    }

    /**
     * @param $id
     * @throws UserNotFoundException
     */
    public function findUserById($id)
    {
        if ($this->doExists(['id' => $id])) {
            $model = $this->createModel();
            $user = $model->newQuery()->find($id);
            return $user;
        } else {
            throw new UserNotFoundException(trans('user-management::messages.user_not_found'), array(trans('user-management::messages.user_not_found')));
        }
    }

    /**
     * @param null $name
     * @return Groups
     * @throws GroupExistsException
     * @throws NameRequiredException
     */
    public function createGroup($name = null)
    {

        if (is_null($name) || strlen($name) <= 0) {
            throw new NameRequiredException(trans('user-management::messages.group_name_required'), array(trans('user-management::messages.group_name_required')));
        }

        if (Groups::where('name', $name)->count() > 0) {
            throw new GroupExistsException(trans('user-management::messages.group_already_required'), array(trans('user-management::messages.group_already_required')));
        } else {
            $group = new Groups();
            $group->name = $name;
            $group->save();

            return $group;
        }
    }

    /**
     * @return array|static[]
     */
    public function allGroups()
    {
        return DB::table($this->groups_table)->select('*')->get();
    }

    /**
     * @param $arr
     * @throws GroupNotFoundException
     */
    public function deleteGroup($arr)
    {

        try {

            if (is_numeric($arr)) {
                $group = Groups::findOrFail($arr);
            } else {
                $group = Groups::where("name", Str::lower($arr));
            }

            $group->delete();

        } catch (ModelNotFoundException $e) {
            throw new GroupNotFoundException(trans('user-management::messages.group_not_found'), [trans('user-management::messages.group_not_found')]);
        }

    }

    /**
     * @param $group_id
     * @throws GroupNotFoundException
     */
    public function findGroupById($group_id)
    {
        try {
            return Groups::find($group_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new GroupNotFoundException(trans('user-management::messages.group_not_found'), array(trans('user-management::messages.group_not_found')));
        }

    }

    /**
     * @param $group_name
     * @throws GroupNotFoundException
     */
    public function findGroupByName($group_name)
    {

        $grp = DB::table($this->groups_table)->where("name", $group_name)->first();

        if (sizeof($grp)>0) {
            return $grp;
        } else {
            throw new GroupNotFoundException(trans('user-management::messages.group_not_found'), array(trans('user-management::messages.group_not_found')));
        }
    }

    /**
     * @param array $conditions
     * @return bool
     */
    public function doExists(array $conditions)
    {

        $table = DB::table($this->users_table);

        foreach ($conditions as $key => $value) {
            $table->where($key, $value);
        }

        return (boolean)$table->count() > 0 ? true : false;

    }

    /**
     * @param $id
     * @return Throttle
     */
    public function findThrottlerByUserId($id)
    {

        if (DB::table($this->throttle_table)->where("user_id", $id)->count() > 0) {
            //Throttle exists
            return Throttle::where("user_id", $id)->first();
        } else {

            //Create new throttle
            $throttle = new Throttle();
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

    /**
     * @return string
     */
    public function generateResetCode()
    {

        $code = Crypt::encrypt(str_random(12));

        if (DB::table($this->users_table)->where("reset_password_code", $code)->count() > 0) {
            $this->generateResetCode();
        }

        return $code;
    }

    /**
     * @param $email
     * @param $remember
     * @param bool $check_throttle
     * @throws UserBannedException
     * @throws UserNotActivatedException
     * @throws UserNotFoundException
     * @throws UserSuspendedException
     */
    public function loginWithID($email,$remember,$check_throttle = true){

        $user = Users::where("email", $email)->first();

        if (sizeof($user) <= 0) {
            throw new UserNotFoundException(trans('user-management::messages.account_not_found'), [trans('user-management::messages.account_not_found')]);
        }

        if ($check_throttle) {

            //Check Activated .
            if ($user->activated!=1) {
                throw new UserNotActivatedException(trans('user-management::messages.user_not_activated'), [trans('user-management::messages.user_not_activated')]);
            }

            //Check banned .
            if (Throttle::where("user_id", $user->id)->where("banned", 1)->count() > 0) {
                throw new UserBannedException(trans('user-management::messages.user_banned'), [trans('user-management::messages.user_banned')]);
            }

            //Check suspended .
            if (Throttle::where("user_id", $user->id)->where("suspended", "1")->count() > 0) {
                throw new UserSuspendedException(trans('user-management::messages.user_suspended'), [trans('user-management::messages.user_suspended')]);
            }
        }

        if(Auth::loginUsingId($user->id,$remember)){
            return Auth::user();
        }else{
            throw new UserNotFoundException(trans('user-management::messages.account_not_found'), [trans('user-management::messages.account_not_found')]);
        }
    }

    /**
     * @param $credentials
     * @param $remember
     * @param bool $check_throttle
     * @throws LoginFieldsMissingException
     * @throws UserBannedException
     * @throws UserNotActivatedException
     * @throws UserNotFoundException
     * @throws UserSuspendedException
     */
    public function login($credentials, $remember, $check_throttle = true)
    {

        if (strlen($credentials['email'])<=0||strlen($credentials['password'])<=0) {
            throw new LoginFieldsMissingException(trans('user-management::messages.email_password_missing'), [trans('user-management::messages.email_password_missing')]);
        }

        if ($check_throttle) {

            $user = Users::where("email", $credentials['email'])->first();

            if (sizeof($user) <= 0) {
                throw new UserNotFoundException(trans('user-management::messages.account_not_found'), [trans('user-management::messages.account_not_found')]);
            }

            //Check Activated .
            if ($user->activated!=1) {
                throw new UserNotActivatedException(trans('user-management::messages.user_not_activated'), [trans('user-management::messages.user_not_activated')]);
            }

            //Check banned .
            if (Throttle::where("user_id", $user->id)->where("banned", 1)->count() > 0) {
                throw new UserBannedException(trans('user-management::messages.user_banned'), [trans('user-management::messages.user_banned')]);
            }

            //Check suspended .
            if (Throttle::where("user_id", $user->id)->where("suspended", "1")->count() > 0) {
                throw new UserSuspendedException(trans('user-management::messages.user_suspended'), [trans('user-management::messages.user_suspended')]);
            }
        }

        if(Auth::attempt($credentials, $remember)){
            return Auth::user();
        }else{
            throw new UserNotFoundException(trans('user-management::messages.account_not_found'), [trans('user-management::messages.account_not_found')]);
        }
    }

    /**
     *
     */
    public function logout()
    {
        Session::flush();
        Auth::logout();
        return true;
    }
} 