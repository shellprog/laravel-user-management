<?php namespace KodeInfo\UserManagement\Exceptions;

//Generic
class NameRequiredException extends AuthException{};
class ValidationException extends AuthException{};

//Groups
class GroupExistsException extends AuthException{};
class GroupNotFoundException extends AuthException{};

//Users
class UserNotFoundException extends AuthException{};
class UserIdentityRequiredException extends AuthException{};
class UserAlreadyExistsException extends AuthException{};
class UserNotActivatedException extends AuthException{};
class ColumnNotFoundException extends AuthException{};

//Throttle
class UserBannedException extends AuthException{};
class UserSuspendedException extends AuthException{};

class LoginFailedException extends AuthException{};
class LoginFieldsMissingException extends AuthException{};
class LoginNotFoundException extends AuthException{};
class LoginValidatorException extends AuthException{};