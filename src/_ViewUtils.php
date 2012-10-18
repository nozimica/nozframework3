<?php
require_once 'HTML/QuickForm2.php';

class FormTplParser extends HTML_QuickForm2 {
    var $htmlViewObj;

    /**
     * Constructor
     *
     * The same parameters than its parent, but the trackSubmit default value is false.
     */
    public function __construct($id, $method = 'post', $attributes = null, $trackSubmit = false) 
    {
        parent::__construct($id, $method, $attributes, $trackSubmit);
    }

    public function loadTemplate($tplName) 
    {
        $this->htmlViewObj = new HtmlView($tplName);
    }

    public function setVariable($varName, $varValue) 
    {
        $this->htmlViewObj->setVariable(strtoupper($varName), $varValue);
    }

    public function toHtml() 
    {
        foreach ($this as $elem_i) {
            $type_i = $elem_i->getType();
            if ($type_i == 'radio') {
                $this->htmlViewObj->setVariable(strtoupper($elem_i->getId()), $elem_i);
            } else {
                $this->htmlViewObj->setVariable(strtoupper($elem_i->getName()), $elem_i);
            }
        }
        return $this->htmlViewObj->toHtml();
    }

    /**
     * Parses a form definition from Model.
     *
     * Text:     ( 'text'     , value     , attributes)
     * Textarea: ( 'textarea' , value     , attributes)
     * Select:   ( 'select'   , options   , selectedOptions , attributes)
     * Radio:    ( 'radio'    , values    , selectedValue   , attributes)
     * Checkbox: ( 'checkbox' , ifChecked , attributes)
     */
    public function parseElements($elemsDefs)
    {
        $groupSeparator = '<span style="float: left;">&nbsp;</span>';
        $rutSeparator = '<span style="float: left;">-</span>';
        $dataSource = array();
        foreach ($elemsDefs as $elemKey => $elemInfo) {
            $required = false;
            // Test if field is required
            if ($elemInfo[0][0] == '*') {
                $required = true;
                $elemInfo[0] = substr($elemInfo[0], 1);
            }
            // Fill arrays to avoid testing of index existence.
            for ($idx = count($elemInfo); $idx < 4; $idx++) {
                $elemInfo[$idx] = null;
            }
            // Adding elements to the form object
            if ($elemInfo[0] == 'text') {
                $elem = $this->addText($elemKey, $elemInfo[2]);
                if (null != $elemInfo[1])    $dataSource[$elemKey] = $elemInfo[1];
            } elseif ($elemInfo[0] == 'textarea') {
                $elemInfo[2] = (array)($elemInfo[2]);
                $elemInfo[2]['rows'] = 4;
                $elemInfo[2]['cols'] = 40;
                $elem = $this->addTextarea($elemKey, $elemInfo[2]);
                if (null != $elemInfo[1])    $dataSource[$elemKey] = $elemInfo[1];
            } elseif ($elemInfo[0] == 'select') {
                $elem = $this->addSelect($elemKey, $elemInfo[3]);
                if (is_array($elemInfo[1])) {
                    $elem->loadOptions($elemInfo[1]);
                }
                if (null != $elemInfo[2])    $dataSource[$elemKey] = $elemInfo[2];
            } elseif ($elemInfo[0] == 'radio' && is_array($elemInfo[1])) {
                $attrArr = (is_array($elemInfo[3])) ? $elemInfo[3] : array();
                foreach ($elemInfo[1] as $val_i) {
                    $attrArr['value'] = $val_i;
                    $elem = $this->addRadio($elemKey, $attrArr);
                }
                if (null != $elemInfo[2])    $dataSource[$elemKey] = $elemInfo[2];
            } elseif ($elemInfo[0] == 'checkbox') {
                $elem = $this->addCheckbox($elemKey, $elemInfo[2]);
                if (null != $elemInfo[1])    $dataSource[$elemKey] = 1;
            } elseif ($elemInfo[0] == 'hidden') {
                $elem = $this->addHidden($elemKey, $elemInfo[2]);
                if (null != $elemInfo[1])    $dataSource[$elemKey] = $elemInfo[1];
            }
            // Adding custom elements to the form
            elseif ($elemInfo[0] == 'rut') {
                $group = $this->addElement('group', $elemKey)->setSeparator($rutSeparator);
                $group->addText("$elemKey-numero", array('size' => 10, 'maxlength' => 10));
                $group->addText("$elemKey-dv", array('size' => 2, 'maxlength' => 2));
                if (null != $elemInfo[1])    $dataSource["$elemKey-numero"] = $elemInfo[1];
            } elseif ($elemInfo[0] == 'name') {
                $elemInfo[2] = (array)($elemInfo[2]);
                $elemInfo[2]['size'] = 14;
                $elem = $this->addText($elemKey, $elemInfo[2]);
                if (null != $elemInfo[1])    $dataSource[$elemKey] = $elemInfo[1];
            } elseif ($elemInfo[0] == 'completename') {
                $group = $this->addElement('group', $elemKey)->setSeparator($groupSeparator);
                $group->addText("nombrePila", array('size' => 14));
                $group->addText("apPat", array('size' => 14));
                $group->addText("apMat", array('size' => 14));
            } elseif ($elemInfo[0] == 'phone') {
                $group = $this->addElement('group', $elemKey)->setSeparator($groupSeparator);
                $group->addText("$elemKey-codigo", array('size' => 2, 'maxlength' => 2));
                $group->addText("$elemKey-numero", array('size' => 10, 'maxlength' => 10));
                if (null != $elemInfo[1])    $dataSource["$elemKey-numero"] = $elemInfo[1];
            } elseif ($elemInfo[0] == 'mobile') {
                $mobileCodes = array('9' => '9', '8' => '8', '7' => '7', '6' => '6');
                $group = $this->addElement('group', $elemKey)->setSeparator($groupSeparator);
                $group->addSelect("$elemKey-codigo")->loadOptions($mobileCodes);
                $group->addText("$elemKey-numero", array('size' => 10, 'maxlength' => 10));
                if (null != $elemInfo[1])    $dataSource["$elemKey-numero"] = $elemInfo[1];
            }
        }
        // Loading values
        if (count($dataSource) > 0) {
            $this->addDataSource(new HTML_QuickForm2_DataSource_Array($dataSource));
        }
    }
}
