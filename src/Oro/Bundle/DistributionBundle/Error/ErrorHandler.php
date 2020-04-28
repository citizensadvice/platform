<?php

namespace Oro\Bundle\DistributionBundle\Error;

class ErrorHandler
{
    /**
     * @var array
     */
    private $errorTypes = array(
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated',
        E_ERROR => 'Error',
        E_CORE_ERROR => 'Core Error',
        E_COMPILE_ERROR => 'Compile Error',
        E_PARSE => 'Parse',
    );

    /**
     * Register all custom application error handlers
     */
    public function registerHandlers()
    {
        $errorTypes = E_RECOVERABLE_ERROR | E_ERROR | E_USER_ERROR | E_WARNING | E_USER_WARNING;
        set_error_handler(array($this, 'handle'), $errorTypes);
    }


    /**
     * @param int    $code
     * @param string $message
     * @param string $file
     * @param int    $line
     *
     * @return bool
     * @throws \ErrorException
     */
    public function handle($code, $message, $file, $line)
    {
        /**
         * Check if suppress warnings used
         */
        if (error_reporting() === 0) {
            return false;
        }

        switch ($code) {
            case E_WARNING:
            case E_USER_WARNING:
                return $this->handleWarning($code, $message);
                break;
            case E_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                $this->handleError($code, $message, $file, $line);
                break;
        }

        return false;
    }

    /**
     * @param int $number
     * @param string $string
     * @deprecated since 1.7 it will be protected after 1.9
     *
     * @return bool
     */
    public function handleWarning($number, $string)
    {
        // silence warning from php_network_getaddresses due to https://magecore.atlassian.net/browse/BAP-3979
        if (strpos($string, 'php_network_getaddresses') !== false) {
            return true;
        }

        /**
         * PHP 7.2 introduced a new warning when calling the "count()" function on non countable
         * parameters. This warning does not change the behaviour of count when called in this 
         * way, but rather is an alert to prevent accidental masking of errors.
         * 
         * Since the ORO upgrade, we are now observing these errors comming from Symfony Core,
         * Twig and the ORO frameworks. As such, it would be too large a task to individually
         * suppress them. Instead, we can safely globally silence the warning here.
         * 
         * @see https://www.php.net/manual/en/migration72.incompatible.php#migration72.incompatible.warn-on-non-countable-types
         */
        if ($string == 'count(): Parameter must be an array or an object that implements Countable') {
            return true;
        }

        /**
         * PHP 7.3 introduced a new warning when calling the "continue" operator as part of switch
         * control flow. This warning does not change the behaviour of continue when called in this 
         * way, but rather is an alert to prevent accidental masking of errors.
         * 
         * As with the above, this is used extensively in code. As such, it would be too large 
         * a task to individually suppress them. Instead, we can safely globally silence 
         * the warning here.
         * 
         * @see https://www.php.net/manual/en/migration73.incompatible.php#migration73.incompatible.core.continue-targeting-switch
         */
        if ($string == '"continue" targeting switch is equivalent to "break". Did you mean to use "continue 2"?') {
            return true;
        }

        return false;
    }

    /**
     * @param int    $code
     * @param string $message
     * @param string $file
     * @param int    $line
     *
     * @throws \ErrorException
     */
    protected function handleError($code, $message, $file, $line)
    {
        $errorType = isset($this->errorTypes[$code]) ? $this->errorTypes[$code] : "Unknown error ({$code})";
        throw new \ErrorException("{$errorType}: {$message} in {$file} on line {$line}", 0, $code, $file, $line);
    }
}
