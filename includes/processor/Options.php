<?php
/**
 * Created by Options.php
 * User: Coder.yee
 * Date: 2024/5/10
 * Time: 17:13
 */
namespace Netsmax\GatewayWoo\processor;

class Options{

    public static function getVersion()
    {
        return (string)get_option( 'netsmax_gateway_for_woocommerce_version' );
    }

    public static function setVersion(string $version)
    {
        return update_option('netsmax_gateway_for_woocommerce_version', $version);
    }
    public static function delVersion()
    {
        return delete_option('netsmax_gateway_for_woocommerce_version');
    }

    /**
     * 一串由UUID生成的随机数字符串
     * @return string
     */
    public static function getStoreId()
    {
        return (string)get_option( 'netsmax_gateway_for_woocommerce_store_id' );
    }

    public static function setStoreId(string $store_id)
    {
        return update_option('netsmax_gateway_for_woocommerce_store_id', $store_id);
    }
    public static function delStoreId()
    {
        return delete_option('netsmax_gateway_for_woocommerce_store_id');
    }

    public static function delSettings()
    {
        //Since the payment extension configuration information of WooCommerce is stored with the prefix "woocommerce", I can only follow the variable naming rules with the prefix "woocommerce".
        return delete_option( 'woocommerce_netsmax-gateway-for-woocommerce_settings' );
    }
}