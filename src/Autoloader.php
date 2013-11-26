<?php

class Autoloader
{
    public static function getLoader()
    {
        set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
        require_once __DIR__ . '/Controller.php';
        require_once __DIR__ . '/Model/FactoryModel.php';
        require_once __DIR__ . '/Model/Model.php';
        require_once __DIR__ . '/View/View.php';
        require_once __DIR__ . '/Authentication/FactoryAuth.php';

        require_once __DIR__ . '/../vendor/autoload.php';
        Twig_Autoloader::register();
    }
}
