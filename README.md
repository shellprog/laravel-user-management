Laravel- User Management
======================

Laravel User Management Made Easy

Add Service Provider to providers array 

'KodeInfo\UserManagement\UserManagementServiceProvider',

In Controller 

use KodeInfo\UserManagement\UserManagement;

public $userManager;

function __construct(UserManagement $userManager){
   $this->userManager = $userManager;
}