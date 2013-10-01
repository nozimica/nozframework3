<?php
require_once "vendor/Twig/lib/Twig/Autoloader.php";
Twig_Autoloader::register();

class FactoryView {
    static public function CreateByOutFormat($outFormat) {
        if ($outFormat == 'html' || $outFormat == 'html-in' || $outFormat == 'html-start') {
            return new HtmlViewBuilder();
        }
        if ($outFormat == 'ajax') {
            return new AjaxViewBuilder();
        }
        return null;
    }
}

/**
 * ViewBase class is the inner core for any View implementation.
 */
class ViewBase {
    protected $controllerObj;

    public function setControllerObj(Controller $obj)
    {
        $this->controllerObj = $obj;
    }
}

/**
 * Interface to be used by the main view implementarion, usually the HTML one.
 */
interface iBasicView {
    public function nfw_printMessage($msg);
    public function nfw_loginAction($res, $afterLogin);
    public function nfw_dieWithMessage($msg);
}

class AjaxViewBase extends ViewBase {
    public function show($msg)
    {
        echo $msg;
    }
}

class HtmlViewBase extends ViewBase implements iBasicView {
    protected $mainView;
    protected $contentView;

    public function __construct() {
        $this->mainView = new HtmlView();
    }

    protected function _completeParsing($token = 'CONTENIDO') {
        $this->mainView->setVariable($token, $this->contentView->toHtml());
        $this->mainView->show();
    }

    public function nfw_printMessage($msg) {
        echo $msg;
    }

    public function nfw_loginAction($resultArr, $afterLogin)
    {
        $this->buildPage('_ingresar', array('LOGIN_MSG' => $resultArr, 'ACTION' => $afterLogin));
    }

    public function nfw_dieWithMessage($msg)
    {
        $this->mainView->setVariable('CONTENIDO', $msg);
        $this->mainView->show();
    }

    public function buildPage($tplName, $varsArr)
    {
        $this->contentView = new HtmlView($tplName);
        foreach ($varsArr as $varName => $varValue) {
            $this->contentView->setVariable($varName, $varValue);
        }
        $this->_completeParsing();
    }
}

/**
 * Template Class
 */
class HtmlView {
    protected $templateObj;
    protected $templPath = "templates";
    protected $twigObj;
    protected $templateObjTwig;
    protected $replacements;

    public function __construct($action = null) {
        $this->twigObj = new Twig_Environment(new Twig_Loader_Filesystem('templates'), array('autoescape' => false));
        $this->replacements = array();
        if (is_null($action)) {
            $this->templateObjTwig = $this->twigObj->loadTemplate("_main.tpl.html");
            $baseDir = dirname($_SERVER['SCRIPT_NAME']);
            if ($baseDir == '/')    $baseDir = '';
            $this->setVariable('PROJ_ROOT', $baseDir);
        } else {
            $this->templateObjTwig = $this->twigObj->loadTemplate("$action.tpl.html");
        }
    }

    public function setVariable($varName, $varValue) {
        $this->replacements[$varName] = $varValue;
    }

    public function show($msg=null) {
        echo $this->toHtml();
    }

    public function toHtml() {
        return $this->templateObjTwig->render($this->replacements);
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
