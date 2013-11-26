<?php

class Autoloader
{
    public static function getLoader()
    {
        set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
        require_once __DIR__ . '/Controller.php';
        require_once __DIR__ . '/Model/FactoryModel.php';
        require_once __DIR__ . '/Model/Model.php';
        require_once __DIR__ . '/View/FactoryView.php';
        require_once __DIR__ . '/View/iBasicView.php';
        require_once __DIR__ . '/View/ViewBase.php';
        require_once __DIR__ . '/View/HtmlViewBase.php';
        require_once __DIR__ . '/View/HtmlView.php';
        require_once __DIR__ . '/View/AjaxViewBase.php';
        require_once __DIR__ . '/View/JsonViewBase.php';
        require_once __DIR__ . '/Authentication/FactoryAuth.php';

        require_once __DIR__ . '/../vendor/autoload.php';
        Twig_Autoloader::register();
    }
}
