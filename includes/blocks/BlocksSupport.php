<?php
/**
 * Created by BlocksSupport.php
 * User: Coder.yee
 * Date: 2024/5/10
 * Time: 16:26
 */
namespace Netsmax\GatewayWoo\blocks;

use Netsmax\GatewayWoo\processor\Sessions;
use Netsmax\GatewayWoo\util\Request;

class BlocksSupport extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType{

    protected $name = 'netsmax-gateway-for-woocommerce';

    public function initialize()
    {
        // Since the payment extension configuration information of WooCommerce is stored with the prefix "woocommerce", I can only follow the variable naming rules with the prefix "woocommerce".
        $this->settings = get_option('woocommerce_netsmax-gateway-for-woocommerce_settings', []);
        add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'failed_payment_notice'], 8, 2);
        add_action('wp_enqueue_scripts', [$this, 'addJsLegacy']);

    }


    public function is_active(): bool
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $version = NETSMAX_GATEWAY_FOR_WOOCOMMERCE_VERSION . (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.' . time() : '');
        $script_asset_path = plugins_url('/assets/js/blocks/blocks.asset.php', NETSMAX_GATEWAY_FOR_WOOCOMMERCE_MAIN_FILE);
        $script_asset      = file_exists($script_asset_path) ? require $script_asset_path : [
            'dependencies' => [],
            'version'      => $version,
        ];
        $script_url        = plugins_url('/assets/js/blocks/blocks.js', NETSMAX_GATEWAY_FOR_WOOCOMMERCE_MAIN_FILE);
        wp_register_script(
            'netsmax-gateway-for-woocommerce-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('netsmax-gateway-for-woocommerce-blocks', 'netsmax-gateway-for-woocommerce',);
        }

        return ['netsmax-gateway-for-woocommerce-blocks'];
    }
    function addJsLegacy(){
        //  append JS
        wp_add_inline_script( 'netsmax-gateway-for-woocommerce-blocks', '' );
        wp_localize_script('netsmax-gateway-for-woocommerce-blocks', 'netsmax_gateway_for_woocommerce_inline_params',
            ['netsmax_gateway_for_woocommerce_inline_time' => time()]);
    }
    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        $get_gateways = WC()->payment_gateways();
        $gateways     = $get_gateways->payment_gateways();

        /**
         * @var \Netsmax\GatewayWoo\services\Gateway $gateway
         */
        $gateway   = $gateways[$this->name];
        $requestId = Request::getRequestId();
        $userId    = get_current_user_id();
        Sessions::setRequestId($requestId);
        return array_replace_recursive(
            [],
            [
                'name'         => $this->get_name(),
                'title'        => $gateway->title,
                'description'  => $gateway->description,
                'button_name'  => __('Confirm payment', 'netsmax-gateway-for-woocommerce'),
                'supports'     => array_filter($gateway->supports, [$gateway, 'supports']),
             //   'saved_cards'  => false,
                'isAdmin'      => is_admin(),
                'payment_page' => $this->get_setting('payment_page'),
                'inline_url'   => $this->get_setting('payment_page') === 'inline' ? $gateway->api()->getInlineUrl
                ($userId, $requestId) : '',
                'request_id'   => $requestId,
                'user_token'   => Sessions::getUserToken(),
                'logo_urls'    => [],
                'div_loading'  => 1,
                'icons'        => [],
            ]);
    }

    /**
     * Add failed payment notice to the payment details.
     *
     * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentContext $context Holds context for the payment.
     * @param \Automattic\WooCommerce\StoreApi\Payments\PaymentResult  $result Result object for the payment.
     */
    public function failed_payment_notice(\Automattic\WooCommerce\StoreApi\Payments\PaymentContext $context, \Automattic\WooCommerce\StoreApi\Payments\PaymentResult &$result)
    {
        if ($this->name !== $context->payment_method) {
            return;
        }
        add_action(
            'netsmax-gateway-for-woocommerce_process_payment_error',
            function ($failed_notice) use (&$result) {
                $payment_details                 = $result->payment_details;
                $payment_details['errorMessage'] = wp_strip_all_tags($failed_notice);
                $result->set_payment_details($payment_details);
            }
        );
    }
}