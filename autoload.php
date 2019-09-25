<?php

spl_autoload_register(function ($class_name) {
    $basedir = dirname(__FILE__);
    $class_map = array(
        "phpCAS" => "$basedir/lib/phpCAS/CAS.php",
        "Casauth" => "$basedir/casauth_pi.php",
        "Casauth_attributes" => "$basedir/casauth_attributes.php",
        "Casauth_model" => "$basedir/casauth_model.php",
        "Casauth_Exception" => "$basedir/casauth_exception.php"
    );
    if(isset($class_map[$class_name])) {
        require_once($class_map[$class_name]);
    }
});
