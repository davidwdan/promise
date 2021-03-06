<?php

namespace Interop\Async\Promise;

use Interop\Async\Promise;

/**
 * Global error handler for promises.
 *
 * Callbacks passed to `Promise::when()` should never throw, but they might. Such errors have to be passed to this
 * global error handler to make them easily loggable. These can't be handled gracefully in any way, so we just enable
 * logging with this handler and ignore them otherwise.
 *
 * If handler is set or that handler rethrows, it will fail hard by triggering an E_USER_ERROR leading to script
 * abortion.
 */
final class ErrorHandler
{
    /** @var callable|null */
    private static $callback = null;

    private function __construct()
    {
        // disable construction, only static helper
    }

    /**
     * Set a new handler that will be notified on uncaught errors during promise resolution callback invocations.
     *
     * This callback can attempt to log the error or exit the execution of the script if it sees need. It receives the
     * exception as first and only parameter.
     *
     * As it's already a last chance handler, the script will be aborted using E_USER_ERROR if the handler throws. Thus
     * it's suggested to always wrap the body of your callback in a generic `try` / `catch` block, if you want to avoid
     * that.
     *
     * @param callable|null $onError Callback to invoke on errors or `null` to reset.
     *
     * @return callable|null Previous callback.
     */
    public static function set(callable $onError = null)
    {
        $previous = self::$callback;
        self::$callback = $onError;
        return $previous;
    }

    /**
     * Notifies the registered handler, that an exception occurred.
     *
     * This method MUST be called by every promise implementation if a callback passed to `Promise::when()` throws upon
     * invocation. It MUST NOT be called otherwise.
     */
    public static function notify($error)
    {
        // No type declaration, because of PHP 5 + PHP 7 support.
        if (!$error instanceof \Exception && !$error instanceof \Throwable) {
            // We have this error handler specifically so we never throw from Promise::when, so it doesn't make sense to
            // throw here. We just forward a generic exception to the registered handlers.
            $error = new \Exception(sprintf(
                "Promise implementation called %s with an invalid argument of type '%s'",
                __METHOD__,
                is_object($error) ? get_class($error) : gettype($error)
            ));
        }

        if (self::$callback === null) {
            trigger_error(
                "An exception has been thrown from an Interop\\Async\\Promise::when handler, but no handler has been"
                . " registered via Interop\\Async\\Promise\\ErrorHandler::set. A handler has to be registered to"
                . " prevent exceptions from going unnoticed. Do NOT install an empty handler that just"
                . " does nothing. If the handler is called, there is ALWAYS something wrong.\n\n" . (string) $error,
                E_USER_ERROR
            );

            return;
        }

        try {
            \call_user_func(self::$callback, $error);
        } catch (\Exception $e) {
            // We're already a last chance handler, throwing doesn't make sense, so use a real fatal
            trigger_error(sprintf(
                "An exception has been thrown from the promise error handler registered to %s::set().\n\n%s",
                __CLASS__,
                (string) $e
            ), E_USER_ERROR);
        } catch (\Throwable $e) {
            // We're already a last chance handler, throwing doesn't make sense, so use a real fatal
            trigger_error(sprintf(
                "An exception has been thrown from the promise error handler registered to %s::set().\n\n%s",
                __CLASS__,
                (string) $e
            ), E_USER_ERROR);
        }
    }
}
