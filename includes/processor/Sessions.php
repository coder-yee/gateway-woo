<?php
/**
 * Created by Sessions.php
 * User: Coder.yee
 * Date: 2024/5/10
 * Time: 17:45
 */
namespace Netsmax\GatewayWoo\processor;

use Netsmax\GatewayWoo\util\Request;
use WC_Payment_Token_CC;

class Sessions {

    public static function setRequestId(string $requestId)
    {
        return WC()->session->set('netsmax_gateway_for_woocommerce_request_id', $requestId);
    }
    public static function getRequestId()
    {
        return WC()->session->get('netsmax_gateway_for_woocommerce_request_id');
    }

    public static function setUserToken(string $gateway_id) {
        if (is_admin()) {
            return;
        }
        if(!( isset( WC()->session ) && WC()->session->has_session() ))
            WC()->session->set_customer_session_cookie( true );
        $tokenUser = new WC_Payment_Token_CC(self::getUserToken());
        $tokenUser->set_token(self::getUserToken());
        $tokenUser->set_user_id(get_current_user_id());
        $tokenUser->set_gateway_id($gateway_id);
        $tokenUser->save();
    }


    public static function getUserToken():string {
        $userToken = WC()->session->get('netsmax_gateway_for_woocommerce_user_token_id');
        if(empty($userToken)) {
            $userToken = Request::getUUID();
            WC()->session->set('netsmax_gateway_for_woocommerce_user_token_id', $userToken);
        }
        return $userToken;
    }
    public static function getUserSessionUniqueId():string {
        return md5(WC()->session->get_customer_unique_id());
    }
}