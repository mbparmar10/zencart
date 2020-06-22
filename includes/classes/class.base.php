<?php
/**
 * File contains just the base class
 *
 * @copyright Copyright 2003-2020 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte 2020 May 23 Modified in v1.5.7 $
 */

/**
 * abstract class base
 *
 * any class that wants to notify or listen for events must extend this base class
 *
 */
class base
{
    /**
     * @var array of aliases
     */
    private $observerAliases = ['NOTIFIY_ORDER_CART_SUBTOTAL_CALCULATE' => 'NOTIFY_ORDER_CART_SUBTOTAL_CALCULATE'];

    /**
     * method used to an attach an observer to the notifier object
     *
     * NB. We have to get a little sneaky here to stop session based classes adding events ad infinitum
     * To do this we first concatenate the class name with the event id, as a class is only ever going to attach to an
     * event id once, this provides a unique key. To ensure there are no naming problems with the array key, we md5 the
     * unique name to provide a unique hashed key.
     *
     * @param object Reference to the observer class
     * @param array An array of eventId's to observe
     */
    function attach(&$observer, $eventIDArray)
    {
        foreach ($eventIDArray as $eventID) {
            $nameHash = md5(get_class($observer) . $eventID);
            base::setStaticObserver($nameHash, array('obs' => &$observer, 'eventID' => $eventID));
        }
    }

    /**
     * method used to detach an observer from the notifier object
     * @param object
     * @param array
     */
    function detach($observer, $eventIDArray)
    {
        foreach ($eventIDArray as $eventID) {
            $nameHash = md5(get_class($observer) . $eventID);
            base::unsetStaticObserver($nameHash);
        }
    }

    /**
     * method to notify observers that an event has occurred in the notifier object
     * Can optionally pass parameters and variables to the observer, useful for passing stuff which is outside of the 'scope' of the observed class.
     * Any of params 2-9 can be passed by reference, and will be updated in the calling location if the observer "update" function also receives them by reference
     *
     * @param string $eventID The event ID to notify.
     * @param mixed $param1 passed as value only.
     * @param mixed $param2 passed by reference.
     * @param mixed $param3 passed by reference.
     * @param mixed $param4 passed by reference.
     * @param mixed $param5 passed by reference.
     * @param mixed $param6 passed by reference.
     * @param mixed $param7 passed by reference.
     * @param mixed $param8 passed by reference.
     * @param mixed $param9 passed by reference.
     *
     * NOTE: The $param1 is not received-by-reference, but params 2-9 are.
     * NOTE: The $param1 value CAN be an array, and is sometimes typecast to be an array, but can also safely be a string or int etc if the notifier sends such and the observer class expects same.
     */
    function notify($eventID, $param1 = array(), &$param2 = null, &$param3 = null, &$param4 = null, &$param5 = null, &$param6 = null, &$param7 = null, &$param8 = null, &$param9 = null)
    {
        $this->logNotifier($eventID, $param1, $param2, $param3, $param4, $param5, $param6, $param7, $param8, $param9);

        // handle observers
        // observers can fire either a generic update() method, or a notifier-point-specific updateNotifierPointCamelCased() method. The specific one will fire if found; else the generic update() will fire instead.
        $observers = &base::getStaticObserver();
        if (is_null($observers)) {
            return;
        }

        foreach ($observers as $key => $obs) {
            $hasAlias = $this->eventIdHasAlias($eventID);
            $actualEventId = $eventID;
            $matchMap = [$eventID, '*'];
            if ($hasAlias) {
                $eventAlias = $this->substituteAlias($obs['eventID']);
                $matchMap = [$eventAlias, $obs['eventID'], '*'];
                $actualEventId = $obs['eventID'];
            }
            if (!in_array($obs['eventID'], $matchMap)) {
                continue;
            }
            $method = 'update';
            $testMethod = $method . self::camelize(strtolower($actualEventId), true);
            if (method_exists($obs['obs'], $testMethod))
                $method = $testMethod;
                $obs['obs']->{$method}($this, $actualEventId, $param1, $param2, $param3, $param4, $param5, $param6, $param7, $param8, $param9);
        }
    }

    function & getStaticProperty($var)
    {
        static $staticProperty;
        return $staticProperty;
    }

    function & getStaticObserver()
    {
        return base::getStaticProperty('observer');
    }

    function setStaticObserver($element, $value)
    {
        $observer =  &base::getStaticObserver();
        if (!is_array($observer)) {
            $observer = array();
        }
        $observer[$element] = $value;
    }

    function unsetStaticObserver($element)
    {
        $observer =  &base::getStaticObserver();
        unset($observer[$element]);
    }

    public static function camelize($rawName, $camelFirst = false)
    {
        if ($rawName == "")
            return $rawName;
        if ($camelFirst) {
            $rawName[0] = strtoupper($rawName[0]);
        }
        return preg_replace_callback('/[_-]([0-9,a-z])/', function ($matches) {
            return strtoupper($matches[1]);
        }, $rawName);
    }

    protected function logNotifier($eventID, $param1, $param2, $param3, $param4, $param5, $param6, $param7, $param8, $param9)
    {
        if (!defined('NOTIFIER_TRACE') || NOTIFIER_TRACE == '' || NOTIFIER_TRACE == 'false' || NOTIFIER_TRACE == 'Off') {
            return;
        }
        $file = DIR_FS_LOGS . '/notifier_trace.log';
        $paramArray = (is_array($param1) && count($param1) == 0) ? array() : array('param1' => $param1);
        for ($i = 2; $i < 10; $i++) {
            $param_n = "param$i";
            if ($$param_n !== null) {
                $paramArray[$param_n] = $$param_n;
            }
        }
        global $this_is_home_page, $PHP_SELF;
        $main_page = (isset($this_is_home_page) && $this_is_home_page)
            ? 'index-home'
            : ((IS_ADMIN_FLAG) ? basename($PHP_SELF)
                : (isset($_GET['main_page']) ? $_GET['main_page'] : ''));
        $output = '';
        if (count($paramArray)) {
            $output = ', ';
            if (NOTIFIER_TRACE == 'var_export' || NOTIFIER_TRACE == 'var_dump' || NOTIFIER_TRACE == 'true') {
                $output .= var_export($paramArray, true);
            } elseif (NOTIFIER_TRACE == 'print_r' || NOTIFIER_TRACE == 'On' || NOTIFIER_TRACE === true) {
                $output .= print_r($paramArray, true);
            }
        }
        error_log(strftime("%Y-%m-%d %H:%M:%S") . ' [main_page=' . $main_page . '] ' . $eventID . $output . "\n", 3, $file);
    }

    private function eventIdHasAlias($eventId)
    {
        if (in_array($eventId, $this->observerAliases)) {
            return true;
        }
        return false;
    }

    private function substituteAlias($eventId)
    {
        return $this->observerAliases[$eventId];
    }
}
