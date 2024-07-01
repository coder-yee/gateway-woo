<?php
/**
 * Created by Func.php
 * User: Coder.yee
 * Date: 2024/5/10
 * Time: 16:37
 */
namespace Netsmax\GatewayWoo\processor;

class Func{

    /**
     * wordpress version
     * @return string
     */
    public static function get_wp_version()
    {
        global $wp_version;
        return !empty($wp_version) ? $wp_version : 'null';
    }

}