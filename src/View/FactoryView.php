<?php

class FactoryView
{
    public static function CreateByOutFormat($outFormat) {
        switch ($outFormat) {
            case 'html':
            case 'html-in':
            case 'html-start':
                return new HtmlViewBuilder();
            case 'ajax':
                return new AjaxViewBuilder();
            case 'json':
                return new JsonViewBuilder();
        }
        return null;
    }
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
