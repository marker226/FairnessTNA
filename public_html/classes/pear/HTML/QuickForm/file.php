<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997, 1998, 1999, 2000, 2001 The PHP Group             |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Adam Daniel <adaniel1@eesus.jnj.com>                        |
// |          Bertrand Mansion <bmansion@mamasam.com>                     |
// +----------------------------------------------------------------------+
//
// $Id: file.php,v 1.19 2004/10/09 19:26:00 avb Exp $

require_once("HTML/QuickForm/input.php");

// register file-related rules
if (class_exists('HTML_QuickForm')) {
    HTML_QuickForm::registerRule('uploadedfile', 'callback', '_ruleIsUploadedFile', 'HTML_QuickForm_file');
    HTML_QuickForm::registerRule('maxfilesize', 'callback', '_ruleCheckMaxFileSize', 'HTML_QuickForm_file');
    HTML_QuickForm::registerRule('mimetype', 'callback', '_ruleCheckMimeType', 'HTML_QuickForm_file');
    HTML_QuickForm::registerRule('filename', 'callback', '_ruleCheckFileName', 'HTML_QuickForm_file');
}

/**
 * HTML class for a file type element
 *
 * @author       Adam Daniel <adaniel1@eesus.jnj.com>
 * @author       Bertrand Mansion <bmansion@mamasam.com>
 * @version      1.0
 * @since        PHP4.04pl1
 * @access       public
 */
class HTML_QuickForm_file extends HTML_QuickForm_input
{
    // {{{ properties

    /**
     * Uploaded file data, from $_FILES
     * @var array
     */
    public static $_value = null;

    // }}}
    // {{{ constructor

    /**
     * Class constructor
     *
     * @param     string    Input field name attribute
     * @param     string    Input field label
     * @param     mixed     (optional) Either a typical HTML attribute string
     *                      or an associative array
     * @since     1.0
     * @access    public
     */
    public function HTML_QuickForm_file($elementName = null, $elementLabel = null, $attributes = null)
    {
        //HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        new HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        self::setType('file');
    } //end constructor

    // }}}
    // {{{ setSize()

    /**
     * Sets size of file element
     *
     * @param     int    Size of file element
     * @since     1.0
     * @access    public
     */
    public static function setSize($size)
    {
        self::updateAttributes(array('size' => $size));
    } //end func setSize

    // }}}
    // {{{ getSize()

    /**
     * Returns size of file element
     *
     * @since     1.0
     * @access    public
     * @return    int
     */
    public static function getSize()
    {
        return self::getAttribute('size');
    } //end func getSize

    // }}}
    // {{{ freeze()

    /**
     * Freeze the element so that only its value is returned
     *
     * @access    public
     * @return    bool
     */
    public static function freeze()
    {
        return false;
    } //end func freeze

    // }}}
    // {{{ setValue()

    /**
     * Returns information about the uploaded file
     *
     * @since     3.0
     * @access    public
     * @return    array
     */
    public static function getValue()
    {
        return self::$_value;
    } //end func setValue

    // }}}
    // {{{ getValue()

    /**
     * Sets value for file element.
     *
     * Actually this does nothing. The function is defined here to override
     * HTML_Quickform_input's behaviour of setting the 'value' attribute. As
     * no sane user-agent uses <input type="file">'s value for anything
     * (because of security implications) we implement file's value as a
     * read-only property with a special meaning.
     *
     * @param     mixed    Value for file element
     * @since     3.0
     * @access    public
     */
    public static function setValue($value)
    {
        return null;
    } // end func getValue

    // }}}
    // {{{ onQuickFormEvent()

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param     string    Name of event
     * @param     mixed     event arguments
     * @param     object    calling object
     * @since     1.0
     * @access    public
     * @return    bool
     */
    public static function onQuickFormEvent($event, $arg, &$caller)
    {
        switch ($event) {
            case 'updateValue':
                if ($caller->getAttribute('method') == 'get') {
                    return PEAR::raiseError('Cannot add a file upload field to a GET method form');
                }
                self::$_value = self::_findValue();
                $caller->updateAttributes(array('enctype' => 'multipart/form-data'));
                $caller->setMaxFileSize();
                break;
            case 'addElement':
                self::onQuickFormEvent('createElement', $arg, $caller);
                return self::onQuickFormEvent('updateValue', null, $caller);
                break;
            case 'createElement':
                $className = get_class($this);
                self::$className($arg[0], $arg[1], $arg[2]);
                break;
        }
        return true;
    } // end func onQuickFormEvent

    // }}}
    // {{{ moveUploadedFile()

        /**
     * Tries to find the element value from the values array
     *
     * Needs to be redefined here as $_FILES is populated differently from
     * other arrays when element name is of the form foo[bar]
     *
     * @access    private
     * @return    mixed
     */
    public static function _findValue()
    {
        if (empty($_FILES)) {
            return null;
        }
        $elementName = self::getName();
        if (isset($_FILES[$elementName])) {
            return $_FILES[$elementName];
        } elseif (false !== ($pos = strpos($elementName, '['))) {
            $base = substr($elementName, 0, $pos);
            $idx = "['" . str_replace(array(']', '['), array('', "']['"), substr($elementName, $pos + 1, -1)) . "']";
            $props = array('name', 'type', 'size', 'tmp_name', 'error');
            $code = "if (!isset(\$_FILES['{$base}']['name']{$idx})) {\n" .
                "    return null;\n" .
                "} else {\n" .
                "    \$value = array();\n";
            foreach ($props as $prop) {
                $code .= "    \$value['{$prop}'] = \$_FILES['{$base}']['{$prop}']{$idx};\n";
            }
            return eval($code . "    return \$value;\n}\n");
        } else {
            return null;
        }
    } // end func moveUploadedFile

    // }}}
    // {{{ isUploadedFile()

    /**
     * Moves an uploaded file into the destination
     *
     * @param    string  Destination directory path
     * @param    string  New file name
     * @access   public
     */
    public static function moveUploadedFile($dest, $fileName = '')
    {
        if ($dest != '' && substr($dest, -1) != '/') {
            $dest .= '/';
        }
        $fileName = ($fileName != '') ? $fileName : basename(self::$_value['name']);
        if (move_uploaded_file(self::$_value['tmp_name'], $dest . $fileName)) {
            return true;
        } else {
            return false;
        }
    } // end func isUploadedFile

    // }}}
    // {{{ _ruleIsUploadedFile()

    /**
     * Checks if the element contains an uploaded file
     *
     * @access    public
     * @return    bool      true if file has been uploaded, false otherwise
     */
    public static function isUploadedFile()
    {
        return self::_ruleIsUploadedFile(self::$_value);
    } // end func _ruleIsUploadedFile

    // }}}
    // {{{ _ruleCheckMaxFileSize()

    /**
     * Checks if the given element contains an uploaded file
     *
     * @param     array     Uploaded file info (from $_FILES)
     * @access    private
     * @return    bool      true if file has been uploaded, false otherwise
     */
    public static function _ruleIsUploadedFile($elementValue)
    {
        if ((isset($elementValue['error']) && $elementValue['error'] == 0) ||
            (!empty($elementValue['tmp_name']) && $elementValue['tmp_name'] != 'none')
        ) {
            return is_uploaded_file($elementValue['tmp_name']);
        } else {
            return false;
        }
    } // end func _ruleCheckMaxFileSize

    // }}}
    // {{{ _ruleCheckMimeType()

    /**
     * Checks that the file does not exceed the max file size
     *
     * @param     array     Uploaded file info (from $_FILES)
     * @param     int       Max file size
     * @access    private
     * @return    bool      true if filesize is lower than maxsize, false otherwise
     */
    public static function _ruleCheckMaxFileSize($elementValue, $maxSize)
    {
        if (!empty($elementValue['error']) &&
            (UPLOAD_ERR_FORM_SIZE == $elementValue['error'] || UPLOAD_ERR_INI_SIZE == $elementValue['error'])
        ) {
            return false;
        }
        if (!HTML_QuickForm_file::_ruleIsUploadedFile($elementValue)) {
            return true;
        }
        return ($maxSize >= @filesize($elementValue['tmp_name']));
    } // end func _ruleCheckMimeType

    // }}}
    // {{{ _ruleCheckFileName()

    /**
     * Checks if the given element contains an uploaded file of the right mime type
     *
     * @param     array     Uploaded file info (from $_FILES)
     * @param     mixed     Mime Type (can be an array of allowed types)
     * @access    private
     * @return    bool      true if mimetype is correct, false otherwise
     */
    public static function _ruleCheckMimeType($elementValue, $mimeType)
    {
        if (!HTML_QuickForm_file::_ruleIsUploadedFile($elementValue)) {
            return true;
        }
        if (is_array($mimeType)) {
            return in_array($elementValue['type'], $mimeType);
        }
        return $elementValue['type'] == $mimeType;
    } // end func _ruleCheckFileName

    // }}}
    // {{{ _findValue()

/**
     * Checks if the given element contains an uploaded file of the filename regex
     *
     * @param     array     Uploaded file info (from $_FILES)
     * @param     string    Regular expression
     * @access    private
     * @return    bool      true if name matches regex, false otherwise
     */
    public static function _ruleCheckFileName($elementValue, $regex)
    {
        if (!HTML_QuickForm_file::_ruleIsUploadedFile($elementValue)) {
            return true;
        }
        return preg_match($regex, $elementValue['name']);
    }

    // }}}
} // end class HTML_QuickForm_file;
