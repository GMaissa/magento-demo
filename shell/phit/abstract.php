<?php

require_once dirname(__FILE__) . '/../abstract.php';

/**
 * Phit Abstract calls for Shell Script
 *
 * @category  Phit
 * @package   Phit_Demo
 * @author    Guillaume Maïssa <guillaume@maissa.fr>
 * @copyright 2014 Phit
 */
abstract class Phit_Shell_Abstract extends Mage_Shell_Abstract
{
    const MSG_DEFAULT = 0;
    const MSG_INFO    = 1;
    const MSG_SUCCESS = 2;
    const MSG_WARNING = 3;
    const MSG_ERROR   = 4;

    /**
     * Output colors of different message types
     * @var array $_msgColors
     */
    protected $_msgColors = array(
        0 => "\033[0m",
        1 => "\033[36m",
        2 => "\033[1;32m",
        3 => "\033[1;33m",
        4 => "\033[1;31m"
    );

    /**
     * Label to be displayed before the message
     * @var array $_msgLabels
     */
    protected $_msgLabels = array(
        0 => '',
        1 => '[INFO]    ',
        2 => '[SUCCESS] ',
        3 => '[WARNING] ',
        4 => '[ERROR]   '
    );

    /**
     * Output a message
     *
     * @param string $msg  message to be displayed
     * @param mixed  $type message type
     *
     * @return void
     */
    protected function _outputMsg($msg, $type=false)
    {
        if (!$type || !array_key_exists($type, $this->_msgColors)) {
            $type = self::MSG_DEFAULT;
        }
        $msg = $this->_msgLabels[$type] . $msg;

        if (!$this->getArg('quiet')) {
            if ($this->getArg('color')) {
                $msg = $this->_msgColors[$type] . ' ' . $msg . ' ' . $this->_msgColors[self::MSG_DEFAULT];
            }
            echo $msg . PHP_EOL;
        }
    }
}
