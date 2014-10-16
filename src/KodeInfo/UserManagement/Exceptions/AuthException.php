<?php namespace KodeInfo\UserManagement\Exceptions;

class AuthException extends \Exception {

    protected $errorsMessages;

    function __construct($message,array $errorsMessages)
    {
        $this->errorsMessages = $errorsMessages;

        parent::__construct($message);
    }

    function getErrors(){
        return $this->errorsMessages;
    }

} 