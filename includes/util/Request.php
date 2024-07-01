<?php
/**
 * Created by Request.php
 * User: Coder.yee
 * Date: 2024/5/10
 * Time: 16:24
 */
namespace Netsmax\GatewayWoo\util;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Request {

    private static string $requestId;

    public static function getRequestId():string
    {
        if(empty(self::$requestId))
        {
            self::$requestId = self::getUUID();
        }
        return self::$requestId;
    }

    public static function getUUID():string
    {
        return str_replace('-', '', wp_generate_uuid4());
    }

    public static function getLanguage() {
        $lang = strtolower( get_bloginfo( 'language' ) );
        $lang = str_replace( '_', '-', $lang );
        if ( substr_count( $lang, '-' ) > 1 ) {
            $parts  = explode( '-', $lang );
            $lang = $parts[0] . '-' . $parts[1];
        }
        if ( strpos( $lang, '-' ) !== false ) {
            $parts = explode( '-', $lang );
            if ( 'zh' === $parts[0] && in_array( $parts[1], [ 'tw', 'hk' ], true ) ) {
                $lang = 'zh-hk';
            } else {
                $lang = $parts[0];
            }
        }
        return $lang;
    }

    public static function getClientIp() {
        if(class_exists('\WC_Geolocation') && method_exists('\WC_Geolocation', 'get_ip_address')) {
            return \WC_Geolocation::get_ip_address();
        }
        if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            return (string) rest_is_ip_address( trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) ) );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return '0.0.0.0';
    }

    public static function getTimeMs(): string
    {
        list($tmp1, $tmp2) = explode(' ', microtime());
        return sprintf('%.0f', (floatval($tmp1) + floatval($tmp2)) * 1000);
    }
}