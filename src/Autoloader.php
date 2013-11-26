<?php

class Autoloader
{
    public static function getLoader()
    {
        set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
        require_once __DIR__ . '/_Controller.php';
        require_once __DIR__ . '/_Model.php';
        require_once __DIR__ . '/_View.php';
        require_once __DIR__ . '/_Authentication.php';

        require_once __DIR__ . '/../vendor/autoload.php';
        Twig_Autoloader::register();
    }
}
