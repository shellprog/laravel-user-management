Laravel- User Management
======================

Laravel User Management Made Easy

Add Service Provider to providers array 

'Kodeinfo\UserManagement\UserManagementServiceProvider',

In Controller 

use Kodeinfo\UserManagement\UserManagement;

public $userManager;

function __construct(UserManagement $userManager){
   $this->userManager = $userManager;
}