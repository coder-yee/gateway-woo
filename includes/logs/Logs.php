<?php
/**
 * Created by logs.php
 * User: Coder.yee
 * Date: 2024/5/10
 * Time: 16:20
 */
namespace Netsmax\GatewayWoo\logs;


/**
 * @name Logs
 * @method static void emergency(string $message, array $context = []) System is unusable.
 * @method static void alert(string $message, array $context = []) Action must be taken immediately.
 * @method static void critical(string $message, array $context = []) Critical conditions.
 * @method static void error(string $message, array|string $context = []) Error conditions.
 * @method static void warning(string $message, array $context = []) Warning conditions.
 * @method static void notice(string $message, array $context = []) Normal but significant condition.
 * @method static void info(string $message, array $context = []) Informational messages.
 * @method static void debug(string $message, array $context = []) Debug-level messages.
 */
final class Logs
{
    private static \WC_Logger $_log;
    public static bool $enable   = false; // Log saving: true:enable, false:disable;
    public static string $handle = '';

    public static function log(string $message, $level = \WC_Log_Levels::NOTICE)
    {
        if (class_exists('WC_Logger') && self::$enable) {
            if (empty(self::$_log)) {
                self::$_log = new \WC_Logger();
            }
            self::$_log->add(self::$handle, $message, $level);
        }
    }

    public static function __callStatic($method, $args)
    {
        if(!self::$enable) {
            return;
        }
        $level   = strtoupper($method);
        $message = '';
        $context = [];
        if(!empty($args[0])) {
            $message = (string)$args[0];
        }
        if(!empty($args[1])) {
            $context = (array)$args[1];
        }
        unset($args);
        $logs = @wp_json_encode(['level' => $level, 'message' => esc_html($message), 'context' => wp_unslash($context)],
            $level === 'DEBUG' ?
                JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES :
                0);
        self::log($logs, $level);
    }
}