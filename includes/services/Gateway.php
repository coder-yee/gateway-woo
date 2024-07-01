<?php
/**
 * Created by Gateway.php
 * User: Coder.yee
 * Date: 2024/5/10
 * Time: 16:14
 */
namespace Netsmax\GatewayWoo\services;

use Netsmax\GatewayWoo\client\ApiClient;
use Netsmax\GatewayWoo\processor\Options;
use Netsmax\GatewayWoo\processor\Sessions;
use Netsmax\GatewayWoo\logs\Logs;
use Netsmax\GatewayWoo\util\Request;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Gateway extends \WC_Payment_Gateway {

    /**
     * Version
     *
     * @var string
     */
    public string $version;

    /**
     * Is test mode active?
     *
     * @var bool
     */
    public bool $testmode;

    /**
     * Enable logging.
     *
     * @var bool $enable_logging
     */
    protected bool $enable_logging;

    /**
     * Should orders be marked as complete after payment?
     *
     * @var bool
     */
    protected bool $autocomplete_order;

 //   protected bool $saved_cards;

    /**
     * Payment page type.
     *
     * @var string
     */
    protected string $payment_page;

    /**
     * Test mid.
     *
     * @var string
     */
    protected string $test_merchant_no;

    /**
     * Test md5 key.
     *
     * @var string
     */
    protected string $test_merchant_key;

    /**
     * Live MID.
     *
     * @var string
     */
    protected string $live_merchant_no;

    /**
     * Live secret key.
     *
     * @var string
     */
    protected string $live_merchant_key;

    /**
     * Should the cancel & remove order button be removed on the pay for order page.
     *
     * @var bool
     */
    protected bool $remove_cancel_order_button;
    protected bool $refund_order_auto_gateway_refund;
    protected bool $cancel_order_auto_gateway_refund;

    /**
     * API MID
     *
     * @var string
     */
    public string $merchant_no;

    /**
     * API key
     *
     * @var string
     */
    public string $merchant_key;

    /**
     * Gateway disabled message
     *
     * @var string
     */
    protected string $msg;

    /**
     * @var ApiClient
     */
    protected static ApiClient $_api;

    protected string $webhook_url_order_payment_notify = '';
    protected string $webhook_url_order_payment_callback = '';

    protected string $webhook_url_order_refund_notify = '';
    protected string $standby_server = '';
    private \WC_Payment_Token_CC $tokenUser;


    /**
     * @return ApiClient
     */
    final public function api(): ApiClient
    {
        if ( empty(self::$_api) || empty(self::$_api->get('merchant_no')) ) {
            $server = NETSMAX_GATEWAY_FOR_WOOCOMMERCE_API_SERVER;
            if(!empty($this->standby_server)) {
                $MainDomainName = NETSMAX_GATEWAY_FOR_WOOCOMMERCE_ROOT_DOMAIN;
                $parseUrl       = wp_parse_url(strtolower($this->standby_server));
                // Only allow our domain name
                if (is_array($parseUrl)
                    && !empty($parseUrl['scheme'])
                    && !empty($parseUrl['host'])
                    && stripos($parseUrl['host'], $MainDomainName) !== false
                    && substr($parseUrl['host'], '-' . strlen($MainDomainName)) === $MainDomainName
                ) {
                    $server = $parseUrl['scheme'] . '://' . $parseUrl['host'];
                    if (!empty($parseUrl['port'])) {
                        $server .= ':' . $parseUrl['port'];
                    }
                }
            }
            self::$_api = new ApiClient((string)$this->merchant_no, (string)$this->merchant_key, $server);
        }
        return self::$_api;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->version            = NETSMAX_GATEWAY_FOR_WOOCOMMERCE_VERSION;
        $this->id                 = NETSMAX_GATEWAY_FOR_WOOCOMMERCE_NAME;
        $this->method_title       = __('Netsmax', 'netsmax-gateway-for-woocommerce');
        $this->method_description = sprintf(
            // translators: 1: is netsmax link url, 2: is Terms of Service link url, 3: is Privacy policy link url.
            __('Netsmax provides merchants with the tools and services they need to accept MasterCard, Visa, Verve and local online payments. You will need to <a href="$1%s" target="_blank">Sign up/Sign in</a> for a account, and get your API key before using. <br />By clicking "Install", you agree to the <a href="$2%s" target="_blank">Terms of Service</a> and <a href="$3%s" target="_blank">Privacy policy</a>.', 'netsmax-gateway-for-woocommerce'),
        esc_url('https://www.netsmax.com/'),
        esc_url('https://www.netsmax.com/'),
        esc_url('https://www.netsmax.com/'),
        );

        $this->payment_page = (string)$this->get_option('payment_page');
        $this->has_fields   = $this->payment_page === 'inline';
        $this->supports     = [
            'subscriptions',
            'products',
            'refunds',
            'pre-orders',
            'tokenization',
        ];

        add_action('admin_init', [$this, 'install']);
        # $this->set_session();
        // Load the form fields
        $this->init_form_fields();

        // Load the settings
        $this->init_settings();

        // Get setting values

        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->enabled            = $this->get_option('enabled');
        $this->enable_logging     = $this->get_option('enable_logging') === 'yes';
        $this->testmode           = $this->get_option('testmode') === 'yes';
        $this->autocomplete_order = $this->get_option('autocomplete_order') === 'yes';
        $this->saved_cards        = false;

        $this->test_merchant_no  = $this->get_option('test_merchant_no');
        $this->test_merchant_key = $this->get_option('test_merchant_key');

        $this->live_merchant_no  = $this->get_option('live_merchant_no');
        $this->live_merchant_key = $this->get_option('live_merchant_key');
        $this->standby_server    = $this->get_option('standby_server');

        $this->remove_cancel_order_button       = $this->get_option('remove_cancel_order_button') === 'yes';
        $this->refund_order_auto_gateway_refund = $this->get_option('refund_order_auto_gateway_refund') === 'yes';
        $this->cancel_order_auto_gateway_refund = $this->get_option('cancel_order_auto_gateway_refund') === 'yes';

        $this->merchant_no  = $this->testmode ? $this->test_merchant_no : $this->live_merchant_no;
        $this->merchant_key = $this->testmode ? $this->test_merchant_key : $this->live_merchant_key;

        // 启用日志
        Logs::$enable = $this->testmode && $this->enable_logging;
        Logs::$handle = $this->id;

        $this->webhook_url_order_payment_notify   = WC()->api_request_url('webhook_order_notify_netsmax-gateway-for-woocommerce');
        $this->webhook_url_order_payment_callback = WC()->api_request_url('webhook_order_callback_netsmax-gateway-for-woocommerce');
        $this->webhook_url_order_refund_notify    = WC()->api_request_url('webhook_order_refund_notify_netsmax-gateway-for-woocommerce');

        // Hooks
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('woocommerce_update_options_payment_gateways_netsmax-gateway-for-woocommerce', [$this, 'process_admin_options']);

        add_action('woocommerce_update_option', [$this, 'action_gateway_enabled']);
        add_action('woocommerce_receipt_netsmax-gateway-for-woocommerce', [$this, 'receipt_page']);

        add_action('woocommerce_api_wc_gateway_netsmax-gateway-for-woocommerce', [$this, 'verify_transaction']);
        // Webhook listener/API hook.
        add_action('woocommerce_api_webhook_order_notify_netsmax-gateway-for-woocommerce', [$this, 'process_webhook_order_notify_netsmax']);
        add_action('woocommerce_api_webhook_order_callback_netsmax-gateway-for-woocommerce', [$this, 'process_webhooks_order_callback']);
        add_action('woocommerce_api_webhook_order_refund_notify_netsmax-gateway-for-woocommerce', [$this, 'process_webhook_order_refund_notify_netsmax']);
        add_action('woocommerce_api_webhook_merchant_verification_payment_page_netsmax', [$this, 'process_webhook_merchant_verification_payment_page_netsmax']);
        add_action('woocommerce_api_webhook_sync_order_status_netsmax', [$this, 'process_webhook_sync_order_status_netsmax']);
        // order
        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_payment']);
        add_action('woocommerce_order_status_changed', [$this, 'hook_order_status_changed'], 10, 4);

        // task
        add_filter('cron_schedules', [$this, 'order_sync_cron_schedules']);
        add_action('wp', [$this, 'process_webhooks_order_callback_received']);
        add_action('wp', [$this, 'register_order_sync_cron_netsmax']);
        add_action('woocommerce_order_sync_event_netsmax-gateway-for-woocommerce', [$this, 'task_order_status_sync_callback']);
    }

    public function order_sync_cron_schedules($schedules){
        $schedules['every_3_minute'] = array(
            'interval' => 180,
            'display'  => __('Every Three Minute', 'netsmax-gateway-for-woocommerce')
        );

        $schedules['every_5_minute'] = array(
            'interval' => 300,
            'display'  => __('Every Five Minute', 'netsmax-gateway-for-woocommerce')
        );

        $schedules['every_10_minute'] = array(
            'interval' => 600,
            'display'  => __('Every Ten Minute', 'netsmax-gateway-for-woocommerce')
        );

        $schedules['every_30_minute'] = array(
            'interval' => 1800,
            'display'  => __('Every Thirty Minute', 'netsmax-gateway-for-woocommerce')
        );

        return $schedules;
    }

    public function register_order_sync_cron_netsmax() {

        #  wp_clear_scheduled_hook('woocommerce_order_sync_event_netsmax-gateway-for-woocommerce');
        if ( ! wp_next_scheduled( 'woocommerce_order_sync_event_netsmax-gateway-for-woocommerce' ) ) {
            wp_schedule_event( time(), 'every_3_minute', 'woocommerce_order_sync_event_netsmax-gateway-for-woocommerce' );
        }
    }


    final public function install()
    {
        // apps install
        if(Options::getVersion() != NETSMAX_GATEWAY_FOR_WOOCOMMERCE_VERSION) {
            Options::setVersion(NETSMAX_GATEWAY_FOR_WOOCOMMERCE_VERSION);
        }
        if(empty(Options::getStoreId())) {
            Options::setStoreId(Request::getUUID());
        }
    }


    public function set_session() {
        if (is_admin()) {
            return;
        }
        Sessions::setUserToken($this->id);
    }

    public function is_product() {
        return is_product() || wc_post_content_has_shortcode( 'product_page' );
    }
    /**
     * Checks whether new keys are being entered when saving options.
     */
    final public function process_admin_options() {
        // update settings
        $testmode = $this->get_option('testmode') === 'yes';
        $old_settings = [
            'id'                => $this->get_option_key(),
            'enabled'           => $this->get_option('enabled'),
            'testmode'          => $this->get_option('testmode'),
            'payment_page'      => $this->get_option('payment_page'),
            'test_merchant_no'  => $this->get_option('test_merchant_no'),
            'test_merchant_key' => $this->get_option('test_merchant_key'),
            'live_merchant_no'  => $this->get_option('live_merchant_no'),
            'live_merchant_key' => $this->get_option('live_merchant_key'),
            'merchant_no'       => $testmode ? $this->get_option('test_merchant_no') : $this->get_option('live_merchant_no'),
            'merchant_key'      => $testmode ? $this->get_option('test_merchant_key') : $this->get_option('live_merchant_key'),
        ];
        parent::process_admin_options();
        // Load all old values after the new settings have been saved.
        $testmode = $this->get_option('testmode') === 'yes';
        $new_settings = [
            'id'                => $this->get_option_key(),
            'enabled'           => $this->get_option('enabled'),
            'testmode'          => $this->get_option('testmode'),
            'payment_page'      => $this->get_option('payment_page'),
            'test_merchant_no'  => $this->get_option('test_merchant_no'),
            'test_merchant_key' => $this->get_option('test_merchant_key'),
            'live_merchant_no'  => $this->get_option('live_merchant_no'),
            'live_merchant_key' => $this->get_option('live_merchant_key'),
            'merchant_no'       => $testmode ? $this->get_option('test_merchant_no') : $this->get_option('live_merchant_no'),
            'merchant_key'      => $testmode ? $this->get_option('test_merchant_key') : $this->get_option('live_merchant_key'),
        ];
        $this->action_gateway_enabled($old_settings, $new_settings);
    }
    final public function action_gateway_enabled($old_settings = [], $new_settings = [] )
    {
        if(!is_admin()) {
            return ;
        }
        if(empty($old_settings['id']) || $old_settings['id'] !== $this->get_option_key()) {
            return ;
        }

        $post = $this->get_post_data();
        $data = [
            'gateway_id' => wc_clean( wp_unslash( $post['gateway_id'] ?? 0 ) )
        ];

        if( !empty($data['gateway_id']) && $data['gateway_id'] == $this->id ) {

            $enabled = $this->get_option('enabled') === 'yes' ? 'no' : 'yes';
            $result  = $this->api()->apiAppStatus([ 'usage_status' => $enabled ]);
            if(empty($result['status']) || $result['status'] != 200) {
                wc_add_notice(
                    esc_html__( 'Enabling/disabling application switch operation timed out, please retry processing.', 'netsmax-gateway-for-woocommerce' ),
                    'error' );
            }

        }else if (!empty($new_settings)){

            $settings = [
                'id'                => $new_settings['id'] ?? '',
                'payment_page'      => $new_settings['payment_page'] ?? '',
                'test_merchant_no'  => $new_settings['test_merchant_no'] ?? '',
                'test_merchant_key' => $new_settings['test_merchant_key'] ?? '',
                'live_merchant_no'  => $new_settings['live_merchant_no'] ?? '',
                'live_merchant_key' => $new_settings['live_merchant_key'] ?? '',
                'merchant_no'       => $new_settings['merchant_no'] ?? '',
                'merchant_key'      => $new_settings['merchant_key'] ?? '',
                'enabled'           => $new_settings['enabled'] ?? 'yes',
                'testmode'          => $new_settings['testmode'] ?? 'yes',
            ];

            if( $old_settings['test_merchant_no'] !== $settings['test_merchant_no']
                    || $old_settings['test_merchant_key'] !== $settings['test_merchant_key']
                    || $old_settings['live_merchant_no'] !== $settings['live_merchant_no']
                    || $old_settings['live_merchant_key'] !== $settings['live_merchant_key']
                    || $old_settings['merchant_no'] !== $settings['merchant_no']
                    || $old_settings['merchant_key'] !== $settings['merchant_key']
                    || $old_settings['enabled'] !== $settings['enabled']

             ) {

                $this->api()->set('merchant_no', $settings['merchant_no']);
                $this->api()->set('merchant_key', $settings['merchant_key']);

                $result = $this->api()->apiAppStatus([
                    'usage_status'         => $settings['enabled'],
                    'testmode'             => $settings['testmode'],
                    'payment_page'         => $settings['payment_page'],
                    'old_test_merchant_no' => $old_settings['test_merchant_no'],
                    'new_test_merchant_no' => $settings['test_merchant_no'],
                    'old_live_merchant_no' => $old_settings['live_merchant_no'],
                    'new_live_merchant_no' => $settings['live_merchant_no'],
                ]);

                if(empty($result['status']) || $result['status'] != 200) {
                    $this->add_admin_notices(
                        esc_html__( 'Enabling/disabling application switch operation timed out, please retry processing', 'netsmax-gateway-for-woocommerce' ),
                        'error' );
                }
            }
        }
    }

    public function process_webhooks_order_callback_received() {

        $order_received = wc_clean( wp_unslash( $_GET['order-received'] ?? '' ) );
        $key = wc_clean( wp_unslash( $_GET['key'] ?? '' ) );

        if(empty($order_received) || empty($key)) {
            return;
        }

        $order_key = absint( wc_get_order_id_by_order_key( $key ) );

        if(!empty($order_key) && $order_key == $order_received) {
            $res = $this->syncOrderStatus($order_key);
            Logs::debug(esc_html('process_webhooks_order_callback_received:: order ['. $order_key .'] status update:' .
            ($res === true ? 'true' : 'false')));
        }
    }

    /**
     * Check if merchant details is filled.
     */
    public function admin_notices()
    {
        if(!is_admin() || 'woocommerce_page_wc-settings' != get_current_screen()->id) {
            return ;
        }

        if ($this->enabled == 'no') {
            return;
        }

        if(!isset($_GET['section']) || $_GET['section'] !== $this->id) {
            return;
        }

        // Check required fields.
        if ( empty($this->merchant_no) || empty($this->merchant_key)) {
            echo '<div class="error"><p>'
                . sprintf(
                    // translators: 1: is plugin settings link address
                    esc_html__('Please enter your merchant details %1$s to use this plugin.', 'netsmax-gateway-for-woocommerce'),
                    '<a href="'
                    . esc_url_raw(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id) )
                    . '">here</a>'
                )
                . '</p></div>';
        }
        return;
    }

    /**
     * Check if merchant details is filled.
     */
    public function add_admin_notices($message, $notice_type = 'success', $data = [])
    {
        if(!is_admin()) {
            return ;
        }

        if ($this->enabled == 'no') {
            return;
        }

        echo '<div class="'
            . ($notice_type == 'success' ? 'success' : 'error')
            . '"><p>'
            . esc_html($message)
            . '</p></div>';
        return;
    }

    /**
     * Check gateway is enabled.
     * @return bool
     */
    public function is_available()
    {
        if ($this->enabled == 'no') {
            return false;
        }
        if (!empty($this->merchant_no) && !empty($this->merchant_key)) {
            return true;
        }
        return false;
    }

    /**
     * autocomplete order
     * @return bool
     */
    protected function is_autocomplete_order_enabled($order)
    {
        if (!$order) {
            return false;
        }
        if ($this->autocomplete_order) {
            if (!empty($this->merchant_no) && !empty($this->merchant_key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Admin WC Settings Options.
     */
    public function admin_options()
    {
        echo '<h2>';
        echo esc_html__('Netsmax', 'netsmax-gateway-for-woocommerce');
        echo '</h2>';

        echo '<h4> <strong>';
        echo sprintf(
            // translators: 1: is Transaction webhook url, 2: is Refund webhook url
            esc_html__( 'Optional: To avoid situations where bad network makes it impossible to verify transactions, You can provide the following webhook URL to netsmax. Transaction:[ %1$s ]  Refund: [ %2$s ] ', 'netsmax-gateway-for-woocommerce' ),
            esc_url($this->webhook_url_order_payment_notify),
            esc_url($this->webhook_url_order_refund_notify)
        );
        echo '</strong> </h4>';

        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

/**
 * Initialise Gateway Settings Form Fields.
 */
    public function init_form_fields() {

        $form_fields = array(
            'enabled'                          => array(
                'title'       => __('Enable/Disable', 'netsmax-gateway-for-woocommerce'),
                'label'       => __('Enable Gateway', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Enable payment option on the checkout page.', 'netsmax-gateway-for-woocommerce'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'title'                            => array(
                'title'       => __('Title', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the payment method title which the user sees during checkout.', 'netsmax-gateway-for-woocommerce'),
                'default'     => __('Netsmax', 'netsmax-gateway-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'description'                      => array(
                'title'       => __('Description', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'textarea',
                'description' => __('This controls the payment method description which the user sees during checkout.', 'netsmax-gateway-for-woocommerce'),
                'default'     => __('Make payment using your credit cards.', 'netsmax-gateway-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'testmode'                         => array(
                'title'       => __('Test mode', 'netsmax-gateway-for-woocommerce'),
                'label'       => __('Enable Test Mode', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Test mode enables you to test payments merchant account before going live. Once the LIVE MODE is enabled on your gateway account uncheck this.', 'netsmax-gateway-for-woocommerce'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'payment_page'                     => array(
                'title'       => __('Payment Option', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'select',
                'description' => __('Popup: The payment popup on the page.<br />Redirect: Will redirect the customer to gateway to make payment.<br />Inline: Entering payment information after selecting a payment method.', 'netsmax-gateway-for-woocommerce'),
                'default'     => 'redirect',
               // 'desc_tip'    => true,
                'options'     => array(
                    'popup'    => __('Popup', 'netsmax-gateway-for-woocommerce'),
                    'redirect' => __('Redirect', 'netsmax-gateway-for-woocommerce'),
                    'inline'   => __('Inline', 'netsmax-gateway-for-woocommerce'),
                ),
            ),
            'test_merchant_no'                 => array(
                'title'       => __('Test MID', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Enter your Test MID here.', 'netsmax-gateway-for-woocommerce'),
                'default'     => '',
            ),
            'test_merchant_key'                => array(
                'title'       => __('Test Merchant Key', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'password',
                'description' => __('Enter your Test Merchant Key here', 'netsmax-gateway-for-woocommerce'),
                'default'     => '',
            ),
            'live_merchant_no'                 => array(
                'title'       => __('Live MID', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Enter your Live MID here.', 'netsmax-gateway-for-woocommerce'),
                'default'     => '',
            ),
            'live_merchant_key'                => array(
                'title'       => __('Live Merchant Key', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'password',
                'description' => __('Enter your Live Merchant Key here.', 'netsmax-gateway-for-woocommerce'),
                'default'     => '',
            ),

            'autocomplete_order'               => array(
                'title'       => __('Autocomplete Order', 'netsmax-gateway-for-woocommerce'),
                'label'       => __('Autocomplete Order After Payment', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'checkbox',
                'class'       => 'netsmax-gateway-for-woocommerce-autocomplete-order',
                'description' => __('If enabled, the order will be marked as complete after successful payment', 'netsmax-gateway-for-woocommerce'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'remove_cancel_order_button'       => array(
                'title'       => __('Remove Cancel Order & Restore Cart Button', 'netsmax-gateway-for-woocommerce'),
                'label'       => __('Remove the cancel order & restore cart button on the pay for order page', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'refund_order_auto_gateway_refund' => array(
                'title'       => __('Refund synced to Netsmax', 'netsmax-gateway-for-woocommerce'),
                'label'       => __('When canceling a paid order, a refund request will be automatically initiated to gateway.', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'cancel_order_auto_gateway_refund' => array(
                'title'       => __('Cancel synced to Netsmax', 'netsmax-gateway-for-woocommerce'),
                'label'       => __('When refunding a paid order, a refund request will be automatically initiated to gateway.', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'enable_logging'   => array(
                'title'   => __( 'Enable Logging', 'netsmax-gateway-for-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enabled logging of gateway interactions can be enabled in test mode.(To view the logs, please go to [WooCommerce>status>logs] to view the debugging logs.)', 'netsmax-gateway-for-woocommerce' ),
                'default' => 'no',
            ),
            'standby_server' => array(
                'title'       => __('Standby Server', 'netsmax-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => __('If your region is unable to connect to our server domain name, please contact customer service to obtain an alternative domain name address and fill it in here to access.', 'netsmax-gateway-for-woocommerce'),
                'default'     => '',
            ),
        );
        $this->form_fields = $form_fields;
    }

    /**
     * Outputs scripts used for payment.
     */
    public function payment_scripts()
    {
        if ($this->enabled !== 'yes') {
            return;
        }

        if (isset($_GET['pay_for_order']) || !is_checkout_pay_page()) {
            return;
        }

        $order_key = wc_clean( wp_unslash( urldecode($_GET['key'] ?? '') ) );
        $order_id  = absint(get_query_var('order-pay'));

        if (empty($order_id) || empty($order_key)) {
            return;
        }

        $order = wc_get_order($order_id);

        if(empty($order) || $order->get_order_key() !== $order_key) {
            return;
        }

        if ($this->id !== $order->get_payment_method()) {
            return;
        }

        $response = $this->process_payment_option_redirect( $order_id );

        if(empty($response['result']) || $response['result'] !== 'success' || empty($response['redirect'])) {
            wp_localize_script('netsmax_gateway_for_woocommerce', 'netsmax_gateway_for_woocommerce_payment_params', [
                'failCode'        => 'FAILED',
                'failMessage'     => esc_html($response['error_message'] ?? __('Failed to establish network connection! Please try again later.', 'netsmax-gateway-for-woocommerce')),
            ]);
            return;
        }


        $params = [
            'id'                     => esc_html($this->id . '-popup-payment'),
            'title'                  => esc_html($this->title),
            'payment_page'           => esc_html($this->payment_page),
            'merchant_no'            => esc_html($this->merchant_no),
            'result'                 => esc_html($response['result']),
            'redirect'               => esc_url_raw($response['redirect']),
            'popup_url'              => esc_url_raw($response['popup_url']),
            'transaction_no'         => esc_html($order->get_meta('netsmax_gateway_for_woocommerce_transaction_no')),
            'channel_transaction_no' => esc_html($order->get_meta('netsmax_gateway_for_woocommerce_channel_transaction_no')),
            'request_id'             => esc_html(Request::getRequestId()),
        ];

        $version = NETSMAX_GATEWAY_FOR_WOOCOMMERCE_VERSION . (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.' . time() : '');
        $urls = [
            'url_css_jquery_ui' => $this->api()->getUrl('url_css_jquery_ui'),
            'url_js_jquery_ui'  => $this->api()->getUrl('url_js_jquery_ui'),
            'url_css_inline'    => $this->api()->getUrl('url_css_inline'),
            'url_js_inline'     => $this->api()->getUrl('url_js_inline'),
            'url_js_main'       => plugins_url('assets/js/payment.js', NETSMAX_GATEWAY_FOR_WOOCOMMERCE_MAIN_FILE),
        ];

        wp_enqueue_script('jquery');

        // Prioritize loading built-in jq ui
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-resizable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-button');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

        // When loading fails, load jq ui from netsmax
        if (!wp_script_is('jquery-ui-dialog')) {
            wp_enqueue_script('netsmax_gateway_for_woocommerce-jquery_ui_js', $urls['url_js_jquery_ui'], ['jquery'],
                $version, false);
        }
        if (!wp_style_is('wp-jquery-ui-dialog')) {
            wp_enqueue_style('netsmax_gateway_for_woocommerce-jquery_ui_css', $urls['url_css_jquery_ui'], [],$version,
                false);
        }

        // load netsmax inline
        wp_enqueue_style('netsmax_gateway_for_woocommerce-inline_css', $urls['url_css_inline'],['jquery'], $version);
        wp_enqueue_script('netsmax_gateway_for_woocommerce-inline_js', $urls['url_js_inline'], ['jquery'], $version, false);
        wp_enqueue_script('netsmax_gateway_for_woocommerce', $urls['url_js_main'], ['jquery'], $version, false);
        wp_localize_script('netsmax_gateway_for_woocommerce', 'netsmax_gateway_for_woocommerce_payment_params', $params);
    }

    /**
     * Load admin scripts.
     */
    final public function admin_scripts()
    {
        if (!is_admin() || 'woocommerce_page_wc-settings' !== get_current_screen()->id) {
            return;
        }
        $version = NETSMAX_GATEWAY_FOR_WOOCOMMERCE_VERSION . (SCRIPT_DEBUG ? '.' . time() : '');
        $params = ['plugin_url' => NETSMAX_GATEWAY_FOR_WOOCOMMERCE_PLUGINS_URL];
        wp_enqueue_script('netsmax_gateway_for_woocommerce_admin', plugins_url('assets/js/admin.js',
            NETSMAX_GATEWAY_FOR_WOOCOMMERCE_MAIN_FILE), [], $version, true);
        wp_localize_script('netsmax_gateway_for_woocommerce_admin', 'netsmax_gateway_for_woocommerce_admin_params', $params);
    }

    /**
     * Process the payment.
     * @param int $order_id
     * @return array
     * @throws \WC_Data_Exception
     * @throws \Exception
     */
    public function process_payment($order_id): array
    {
        try{

            switch ($this->payment_page) {
                case 'inline':
                    $response = $this->process_payment_option_inline($order_id);
                    break;
                /*        case 'direct':
                            $response = $this->process_payment_option_direct($order_id);
                            break;*/
                case 'redirect':
                    Sessions::setRequestId(Request::getRequestId());
                    $response = $this->process_payment_option_redirect($order_id);
                    break;
                default:
                    Sessions::setRequestId(Request::getRequestId());
                    $response = $this->process_payment_option_popup($order_id);
                    break;
            }
            if(empty($response['result']) || $response['result'] !== 'success') {
                $this->api()::wc_add_notice(esc_html($response['error_message'] ?? __('Unable to establish network connection or network connection timeout! Please try again later.', 'netsmax-gateway-for-woocommerce')), 'error');
            }
            return $response;
        }catch (\WC_Data_Exception $e) {
            Logs::error( 'process_payment - card payment create failed. ',
                [
                    'errorCode'     => esc_html($e->getCode()),
                    'errorMessage'  => esc_html($e->getMessage()),
                ]);
            $this->api()::wc_add_notice( esc_html($e->getLocalizedMessage()), 'error' );
        }catch (\Exception|\Error $e) {
            Logs::error( 'process_payment - card payment create failed. ',
                [
                    'errorCode'     => esc_html($e->getCode()),
                    'errorMessage'  => esc_html($e->getMessage()),
                ]);
            $this->api()::wc_add_notice( esc_html($e->getMessage()), 'error' );
            throw new \Exception( sprintf(
                // Translators: 1: is method title
                    esc_html__( '%1$s: payment error!', 'netsmax-gateway-for-woocommerce' ), esc_html($this->method_title))
            );
        }
        return [
            'result'   => 'fail',
            'redirect' => '',
        ];
    }

    /*
        private function process_payment_option_direct($order_id) {

            $result = $this->process_payment_option_inline_run($order_id);
            if($result['result'] !== 'success') {
                $order = wc_get_order($order_id);
                $payment_message  =sprintf(__('Payment failed! Failure code: %1$s, failure reason: %2$s.', 'netsmax-gateway-for-woocommerce'), $result['error_code'], $result['error_message'] ?? '-' );
                $order->add_order_note($payment_message);
                $this->api()::wc_add_notice($payment_message, 'error');
            }
            return $result;
        }*/

    /**
     * Iframe嵌入模式
     * @param int $order_id
     * @return array
     */
    private function process_payment_option_inline(int $order_id): array
    {
        $result = $this->process_payment_option_inline_run($order_id);
        if($result['result'] !== 'success') {
            $order = wc_get_order($order_id);
            $payment_message = esc_html( sprintf(
                // Translators: 1: Failure code,  2: failure reason
                __('Payment failed! Failure code: %1$s, failure reason: %2$s.', 'netsmax-gateway-for-woocommerce'),
                $result['error_code'],
                $result['error_message'] ?? '-'
            ) );
            $order->add_order_note($payment_message);
            $this->api()::wc_add_notice($payment_message, 'error');
        }
        return $result;
    }

    /**
     * Iframe嵌入模式付款
     * @param int $order_id
     * @return array
     */
    private function process_payment_option_inline_run(int $order_id): array
    {
        if( $order_id < 1 ) {
            return [
                'result'        => 'fail',
                'redirect'      => '',
                'error_message' => '',
                'error_code'    => '404',
            ];
        }

        $order = wc_get_order($order_id);

        if (empty($order) || $order->get_payment_method() !== $this->id) {
            return [
                'result'        => 'fail',
                'redirect'      => $this->get_return_url($order),
                'error_message' => '',
                'error_code'    => 'PI001',
            ];
        }
        // pending
        $status = strtolower($order->get_status());
        if (!in_array($status, ['pending'/*, 'processing'*/])) {
            return [
                'result'        => 'success',
                'redirect'      => $this->get_return_url($order),
                'error_message' => '',
                'error_code'    => 'PI002',
            ];
        }
        /*
                if ( in_array( $status , array('processing', 'completed', 'on-hold', 'cancelled' )) ) {
                    return true;
                }*/
        // $post 只给 $query 初始化获取使用。
        $post  = $this->get_post_data();
        $query = [
            'is-netsmax-card-block' => wc_clean(wp_unslash($post['is-netsmax-card-block'] ?? '')),
            'prepayment'            => [
                'request_id'      => wc_clean(wp_unslash($post['request_id'] ?? '')),
                'prepayment_id'   => wc_clean(wp_unslash($post['prepayment_id'] ?? '')),
                'prepayment_info' => wc_clean(wp_unslash($post['prepayment_info'] ?? '')),
                'amount'          => wc_clean(wp_unslash($post['amount'] ?? '0')),
                'currency'        => wc_clean(wp_unslash($post['currency'] ?? '')),
            ],
        ];
        if($query['prepayment']['request_id'] !== Sessions::getRequestId()
            || empty($query['prepayment']['request_id'])
            || empty($query['prepayment']['prepayment_id'])
            || empty($query['prepayment']['prepayment_info'])
            || empty($query['prepayment']['currency'])
            || $query['prepayment']['currency'] !== $order->get_currency()
            || (int)$query['prepayment']['amount'] !== absint(bcmul($order->get_total(), 100))
        ) {
            return [
                'result'        => 'fail',
                'redirect'      => $this->get_return_url($order),
                'error_message' => '',
                'error_code'    => 'PI003',
            ];
        }

        $params = array_merge($this->prepayment_order_info($order), $query);

        $headers = [
            'x-app-client-ip'    => esc_attr($order->get_customer_ip_address()),
            'x-app-client-agent' => esc_attr($order->get_customer_user_agent()),
        ];

        $request = $this->api()->apiOrderPaymentCashierInline($params, $headers);
        $result  = $request['data'] ?? [];

        if (!empty($request['status'])
            && !empty($result['returnCode'])
            && $request['status'] == 200
            && $result['returnCode'] === 'SUCCESS'
            && !empty($result['transaction_no'])
            && !empty($result['channel_transaction_no'])
            && !empty($result['payment_status'])
            && in_array($result['payment_status'], array_keys($this->getOrderStatusInfo()))
            && $result['transaction_no'] === $params['transaction_no']
        ) {
            $order         = wc_get_order($order_id);
            $redirect      = $this->get_return_url($order);
            $error_code    = '';
            $error_message = '';
            if($result['payment_status'] === 'PROCESSING') {
                if(!empty($result['redirect_url'])) {
                    $redirect = $result['redirect_url'];
                }
                if(!empty($result['failCode'])) {
                    $error_code    = esc_html($result['failCode']);
                    $error_message = esc_html($result['failMessage'] ?? '');
                }
                $order->add_order_note(
                    esc_html__('In order payment processing, it does not indicate successful payment.', 'netsmax-gateway-for-woocommerce'),
                    1, true);
            }
            $order->add_meta_data('netsmax_gateway_for_woocommerce_channel_transaction_no', esc_html($result['channel_transaction_no']), true);
            $order->update_meta_data('netsmax_gateway_for_woocommerce_payment_url', esc_url_raw($result['redirect_url']));
            $order->update_meta_data('netsmax_gateway_for_woocommerce_payment_status', 'ping');
            $order->save();

            $response = [
                'result'       => 'success',
                'redirect'     => $redirect,
                'payment_page' => $this->get_option('payment_page'),
                'error_message'=> $error_message,
                'error_code'   => $error_code,
            ];
        } else {
            $response = [
                'result'        => 'fail',
                'redirect'      => $order->get_checkout_payment_url(true),
                'payment_page'  => $this->get_option('payment_page'),
                'error_message' => esc_html(!empty($result['failMessage']) ? $result['failMessage'] :
                    __('Unable to process payment try again', 'netsmax-gateway-for-woocommerce')),
                'error_code'    => esc_html(!empty($result['failCode']) ? $result['failCode'] : 'PI000'),
            ];
            if(!empty($result['transaction_no'])) {
                $error_message = sprintf(
                    // Translators: 1: error code,  2: error reason
                    esc_html__('(%1$s) Payment failed! Reason: %2$s', 'netsmax-gateway-for-woocommerce'),
                    esc_html($response['error_code']),
                    esc_html($response['error_message'])
                );
                wc_add_notice($error_message, 'error');
                $order->add_order_note($error_message);
                $order->update_status( 'failed');
            }else{
                $error_message = sprintf(
                    // Translators: 1: error code,  2: error reason
                    esc_html__('(%1$s) Payment exception! Reason: %2$s', 'netsmax-gateway-for-woocommerce'),
                    esc_html($response['error_code']),
                    esc_html($response['error_message'])
                );
                wc_add_notice($error_message, 'error');
                $order->add_order_note($error_message);
            }
        }
        Logs::debug('inline payment response:', $response);
        return $response;
    }


    /**
     * 弹窗模式
     * 跳转支付界面进行 弹窗、跳转付款
     * @param int $order_id
     * @return array
     */
    private function process_payment_option_popup(int $order_id): array
    {
        $order = wc_get_order($order_id);

        // 接收post参数仅做数据比较使用
        $post  = $this->get_post_data();
        $save_card = false;
        if(!empty($post['wc-netsmax-gateway-for-woocommerce-new-payment-method'])) {
            $save_card = true;
        }
        if (is_user_logged_in() && true === $save_card) {
            $order->update_meta_data('netsmax-gateway-for-woocommerce_save_card', true);
            $order->save();
        }
        return [
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }


    /**
     * 跳转模式
     * @param int $order_id
     * @return array
     * @since 5.7
     */
    private function process_payment_option_redirect(int $order_id): array
    {
        $result = $this->process_payment_option_redirect_run($order_id);
        if($result['result'] !== 'success') {
            $order = wc_get_order($order_id);
            $payment_message  =sprintf(
                // Translators: 1: Failure code,  2: failure reason
                esc_html__('Payment failed! Failure code: %1$s, failure reason: %2$s.', 'netsmax-gateway-for-woocommerce'),
                esc_html($result['error_code']),
                esc_html($result['error_message'] ?? '-')
            );
            $order->add_order_note($payment_message);
        }
        return $result;
    }

    /**
     * 跳转模式、Iframe弹窗模式 共用付款方法
     * @param int $order_id
     * @return array
     */
    private function process_payment_option_redirect_run(int $order_id): array
    {
        $order = wc_get_order($order_id);
        if (empty($order) || $order->get_payment_method() !== $this->id) {
            return [
                'result'        => 'fail',
                'redirect'      => '',
                'error_message' => '',
                'error_code'    => 'PR001',
            ];
        }
        $params = $this->prepayment_order_info($order);

        $headers = [
            'x-app-client-ip'    => esc_attr($order->get_customer_ip_address()),
            'x-app-client-agent' => esc_attr($order->get_customer_user_agent()),
        ];

        // 收银台模式，申请预支付，并返回支付URL
        $request = $this->api()->apiOrderCashier($params, $headers);
        $result  = $request['data'] ?? [];
        if (!empty($request['status'])
            && $request['status'] == 200
            && !empty($result['returnCode'])
            && $result['returnCode'] == 'SUCCESS'
            && !empty($result['redirect_url'])
            && !empty($result['channel_transaction_no'])
            && !empty($result['transaction_no'])
            && $result['transaction_no'] == $params['transaction_no']
        ) {
            $order = wc_get_order($order_id);
            $order->add_meta_data('netsmax_gateway_for_woocommerce_channel_transaction_no', esc_html($result['channel_transaction_no']), true);
            $order->update_meta_data('netsmax_gateway_for_woocommerce_payment_url', esc_html($result['redirect_url']));
            $order->update_meta_data('netsmax_gateway_for_woocommerce_payment_status', 'ping');
            $order->save();

            $response = [
                'result'       => 'success',
                'redirect'     => esc_url_raw($result['redirect_url']),
                'popup_url'    => esc_url_raw(!empty($result['popup_url']) ? $result['popup_url'] : ''),
                'payment_page' => $this->get_option('payment_page'),
                'error_message'=> '',
                'error_code'   => '',
            ];
        } else {
            $response = [
                'result'        => 'fail',
                'redirect'      => $order->get_checkout_payment_url(true),
                'popup_url'     => '',
                'payment_page'  => $this->get_option('payment_page'),
                'error_message' => esc_html($result['failMessage'] ?? __('Unable to process payment try again', 'netsmax-gateway-for-woocommerce')),
                'error_code'    => esc_html($result['failCode'] ?? 'PR000'),
            ];
        }
        return $response;
    }


    /**
     * 预支付信息
     * @param \WC_Order $order
     * @return array
     */
    private function prepayment_order_info(\WC_Order &$order): array
    {
        $currency  = $order->get_currency();
        $get_total = $order->get_total();
        $orderHash = md5(sprintf('%s:%s(%s:%s)%s',
            $order->get_payment_method(),
            $order->get_id(),
            $currency,
            $get_total,
            $order->get_formatted_billing_address() . $order->get_formatted_shipping_address(). $order->get_customer_note()
        ));

        $old_tra_no = $order->get_meta('netsmax_gateway_for_woocommerce_transaction_no');
        $old_hash   = $order->get_meta('netsmax_gateway_for_woocommerce_transaction_hash');

        if (empty($old_tra_no) || empty($old_hash) || $old_hash !== $orderHash) {
            $order->update_meta_data('netsmax_gateway_for_woocommerce_transaction_hash', esc_html($orderHash));
            $transactionNo = $this->order_create_transaction_no($order);
            $order->update_meta_data('netsmax_gateway_for_woocommerce_transaction_no', esc_html($transactionNo));
        }

        $order->update_meta_data('netsmax_gateway_for_woocommerce_merchant_no', esc_html($this->merchant_no));
        $order->save();

        // 重组商品信息
        $products = [];
        foreach ($order->get_items() as $item) {
            if (is_a($item, 'WC_Order_Item_Product')) {
                $product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
                $subtotal   = $item->get_subtotal();
            } else {
                $product_id = substr(sanitize_title($item->get_name()), 0, 12);
                $subtotal   = $item->get_total();
            }

            $products[] = [
                'product_id' => $product_id,
                'name'       => $item->get_name(),
                'quantity'   => $item->get_quantity(),
                'currency'   => $currency,
                'unit_price' => absint(
                    wc_format_decimal(
                        bcmul( bcdiv( $subtotal, $item->get_quantity(), 2), 100, 2),
                        wc_get_price_decimals()
                    )
                ),
                'subtotal'   => absint(
                    wc_format_decimal(
                        bcmul( $subtotal, 100, 2),
                        wc_get_price_decimals()
                    )
                ),

            ];
        }

        // 重组接口所需要的参数
        $info = [
            'transaction_id'   => $order->get_id(),
            'transaction_no'   => $order->get_meta('netsmax_gateway_for_woocommerce_transaction_no'),
            'merchant_no'      => $this->merchant_no,
            'payment_page'     => $this->get_option('payment_page'),
            'saas_code'        => 'woocommerce',
            'test'             => $this->testmode ? 1 : 0,
            'currency'         => $order->get_currency(),
            'amount'           => absint( bcmul( $get_total, 100 ) ),
            'reason'           => $order->get_customer_note(),
            'products'         => [
                'products'       => $products,
                'shipping_total' => $order->get_shipping_total(),
                'fees'           => $order->get_total_fees()
            ],
            'email'            => $order->get_billing_email(),
            'billing_address'  => $order->get_address('billing'),
            'shipping_address' => $order->get_address('shipping'),
            'notify_url'       => $this->webhook_url_order_payment_notify,
            'callback_url'     => $this->webhook_url_order_payment_callback,
            'return_url'       => $this->get_return_url($order),
            'cancel_action'    => wc_get_cart_url(),
            'timestamp'        => time(),
        ];
        return $info;
    }

    /**
     * 跳转、弹窗模式时付款界面配置
     * @param $order_id
     */
    public function receipt_page($order_id)
    {
        $order = wc_get_order($order_id);
        echo '<div id="netsmax-gateway-for-woocommerce-form">';
        echo '<p>'
            . esc_html__('Thank you for your order, please click the button below to pay with payment.', 'netsmax-gateway-for-woocommerce')
            . '</p>';
        echo '<div id="netsmax-gateway-for-woocommerce_form"><form id="order_review" method="post" action="'
            . esc_url_raw(WC()->api_request_url('netsmax_gateway_for_woocommerce_online_payment_gateway'))
            . '"></form><button type="button" class="netsmax-gateway-for-woocommerce-btn-payment" id="netsmax-gateway-for-woocommerce-payment-button">'
            . esc_html__('Pay Now', 'netsmax-gateway-for-woocommerce')
            . '</button> '
            . sprintf(
                 // Translators: 1: payment link a,  2: payment link a
                 esc_html__('Or click %1$s here %2$s to redirect to payment.', 'netsmax-gateway-for-woocommerce'),
                '<a href="#" class="netsmax-gateway-for-woocommerce-payment-redirect" id="netsmax-gateway-for-woocommerce-payment-redirect">',
                '</a>'
            );
        if (!$this->remove_cancel_order_button || true) {
            echo '<br /> <a class="netsmax-gateway-for-woocommerce-btn-payment-cancel" id="netsmax-gateway-for-woocommerce-cancel-payment-button" href="'
                . esc_url_raw($order->get_cancel_order_url())
                . '">'
                . esc_html__('Cancel order &amp; restore cart', 'netsmax-gateway-for-woocommerce')
                . '</a>';
        }
        echo '</div>';
        echo '<div id="netsmax-gateway-for-woocommerce-dialog" title="'.
            esc_attr($this->title)
            .'" style="display: none;"></div>';
        echo '</div>';
    }

    /**
     * 异步通知时响应
     * 返回支付平台使用
     * @param $statusCode
     * @param $error
     * @return null
     */
    private function responseExit($statusCode, $error)
    {
        status_header(esc_html($statusCode), esc_html($error));
        header("x-message: " . esc_attr($error));
        echo esc_html($error);
        exit();
    }

    /**
     * 异步通知时响应
     * 返回支付平台使用
     * @param $statusCode
     * @param $error
     * @return null
     */
    private function responseMsg($statusCode, $error)
    {
        status_header(esc_html($statusCode), esc_html($error));
        header("x-message: " . esc_attr($error));
        echo esc_html($error);
        return ;
    }

    /**
     * Process Webhook.
     * Receive asynchronous notification of successful payment.
     *
     * Note: This refers to receiving a notification as only a condition to trigger the query and synchronize the order. Because after receiving the notification data, it will actively request the netsmax server to query the data and synchronize it.
     * @return void|null
     */
    public function process_webhook_order_notify_netsmax()
    {
        try{
            $result = $this->api()->apiGetNotify();
            if ( empty($result) ) {
                return $this->responseExit(403, '');
            }
            if (!is_array($result)) {
                return $this->responseExit(403, (string)$result);
            }

            if (empty($result['returnCode']) || 'SUCCESS' !== $result['returnCode'])
            {
                return $this->responseExit(403, 'Return Code Error.');
            }

            if (empty($result['transaction_id'])
                || empty($result['transaction_no'])
                || empty($result['channel_transaction_no'])) {
                return $this->responseExit(403, 'Transaction No Error.');
            }
            $payStatus = ['PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELED', 'EXPIRED'];
            if (empty($result['payment_status']) || !in_array($result['payment_status'], $payStatus)) {
                return $this->responseExit(403, 'Payment Status Error.');
            }
            $transaction_id = (int)$result['transaction_id'];
            if(empty($transaction_id)) {
                return $this->responseExit(403, 'Transaction Id Error.');
            }

            if ('SUCCEEDED' === $result['payment_status']) {
                $order = wc_get_order($transaction_id);
                if (!$order) {
                    return $this->responseExit(404, 'Order Is Null.');
                }
                if ($order->get_meta('netsmax_gateway_for_woocommerce_payment_status') == $result['payment_status']) {
                    // success
                    return $this->responseExit(200, 'SUCCESS');
                }
            }

            if (true === $this->syncOrderStatus($transaction_id)) {
                // success
                return $this->responseExit(200, 'SUCCESS');
            }

        }catch (\WC_Data_Exception|\Exception|\Error $e){
            Logs::error( 'process_webhook_order_notify_netsmax - order notify error. ',
                [
                    'errorCode'     => esc_html($e->getCode()),
                    'errorMessage'  => esc_html($e->getMessage()),
                ]);
            $this->api()::wc_add_notice( esc_html($e->getMessage()), 'error' );
            return $this->responseExit(500,
                sprintf(
                    // Translators: 1: method title
                    esc_html__( '%1$s order payment notification processing error', 'netsmax-gateway-for-woocommerce' ),
                    esc_html($this->method_title)
                )
            );
        }
        return $this->responseExit(201, 'FAILED');
    }

    /**
     * 同步订单状态
     * @param $order_id
     * @return bool
     */
    private function syncOrderStatus($order_id): bool
    {
        try{
            $order = wc_get_order($order_id);
            if (!$order) {
                return 'order is null';
            }

            if ($order->get_payment_method() !== $this->id) {
                return 'order payment method error';
            }
            if ($order->get_meta('netsmax_gateway_for_woocommerce_merchant_no') != $this->merchant_no) {
                return 'order merchant_no error';
            }
            // pending
            $status = strtolower($order->get_status());

            if ($status === 'trash') {
                return 'order is trash';
            }
            if (!in_array($status, ['pending'/*, 'on-hold', 'processing'*/])) {
                return 'order is not pending';
            }

            /*
                    if ( in_array( $status , array('processing', 'completed', 'on-hold', 'cancelled' )) ) {
                        return true;
                    }*/

            $params = [
                'transaction_id'         => $order->get_id(),
                'transaction_no'         => $order->get_meta('netsmax_gateway_for_woocommerce_transaction_no'),
                'channel_transaction_no' => $order->get_meta('netsmax_gateway_for_woocommerce_channel_transaction_no')
            ];
            $request = $this->api()->apiOrderQuery($params);
            $result  = $request['data'] ?? [];
            if (empty($result['payment_status'])
                || empty($result['transaction_id'])
                || empty($result['transaction_no'])
                || empty($result['channel_transaction_no'])
                || empty($result['amount'])
                || empty($result['currency'])
                || empty($result['returnCode'])
            ) {
                return false;
            }

            if ($order->get_total() * 100 != $result['amount']
                || $order->get_currency() !== $result['currency']
                || $order->get_meta('netsmax_gateway_for_woocommerce_transaction_no') !== $result['transaction_no']
                || $order->get_meta('netsmax_gateway_for_woocommerce_channel_transaction_no') !== $result['channel_transaction_no']
            ) {
                return 'order query api response is error:' . esc_html(wp_json_encode($request));
            }

            $transaction_id = $result['channel_transaction_no'];
            $payStatus      = array_keys($this->getOrderStatusInfo());
            $payment_status = strtoupper($result['payment_status']);
            if (!in_array($payment_status, $payStatus)) {
                return 'order status update is not in.';
            }
            $reason = sprintf(
                // Translators: 1: Failure code,  2: failure reason
                esc_html__('Reason:%1$s-%2$s', 'netsmax-gateway-for-woocommerce'),
                esc_html($result['failCode']),
                esc_html($result['failMessage'])
            );
            #  $order->delete_meta_data('netsmax_gateway_for_woocommerce_channel_transaction_no');
            $order->update_meta_data('netsmax_gateway_for_woocommerce_payment_status', $payment_status);
            if ($payment_status === 'SUCCEEDED') {
                $order->payment_complete($transaction_id);
                $amount = wc_price($order->get_total(), ['currency' => $order->get_currency()]);
                $order->add_order_note(sprintf(
                    // Translators: 1: is method title,  2: is amount, 3: is order transaction id
                    esc_html__('Payment via %1$s %2$s successful(Transaction Reference: %3$s).', 'netsmax-gateway-for-woocommerce'),
                    esc_html($this->method_title),
                    trim($amount),
                    esc_html($transaction_id)
                ));
                if ($this->is_autocomplete_order_enabled($order)) {
                    $order->update_status('completed');
                }
            } elseif ($payment_status === 'CANCELED') {
                $order->update_status('cancelled');
                $order->set_transaction_id($transaction_id);
                $order->add_order_note(sprintf(
                    // Translators: 1: is method title,  2: is payment status, 3: is order transaction id, 4: is reason
                    esc_html__('Payment via %1$s %2$s (Transaction Reference: %3$s), %4$s', 'netsmax-gateway-for-woocommerce'),
                    esc_html($this->method_title),
                    esc_html($this->getOrderStatusInfo($payment_status)),
                    esc_html($transaction_id),
                    esc_html($reason),
                ));
                if(in_array($result['failCode'], ['P0002', 'S0002'])) {
                    $order->add_order_note(
                        sprintf(
                            // Translators: 1: is a order status
                            esc_html__('Order payment failed, this transaction has been %1$s! If you have any questions, please contact customer service. Or place a new order to purchase the product.', 'netsmax-gateway-for-woocommerce'),
                            esc_html($this->getOrderStatusInfo($payment_status)),
                        ),
                        1, true);
                }

            } elseif ($payment_status === 'FAILED' || $payment_status === 'EXPIRED') {
                $order->update_status('failed');
                $order->set_transaction_id($transaction_id);
                $order->add_order_note(
                    sprintf(
                        // Translators: 1: is method title,  2: is payment status, 3: is order transaction id, 4: is reason
                        esc_html__('Payment via %1$s %2$s (Transaction Reference: %3$s), %4$s ', 'netsmax-gateway-for-woocommerce'),
                        esc_html($this->method_title),
                        esc_html($this->getOrderStatusInfo($payment_status)),
                        esc_html($transaction_id),
                        esc_html($reason),
                    )
                );
                $order->add_order_note(
                    sprintf(
                        // Translators: 1: is a order status
                        esc_html__('Order payment failed, this transaction has been %1$s! If you have any questions, please contact customer service. Or place a new order to purchase the product.', 'netsmax-gateway-for-woocommerce'),
                        esc_html($this->getOrderStatusInfo($payment_status)),
                    ),
                    1, true);
            }
            $order->delete_meta_data('netsmax_gateway_for_woocommerce_payment_url');
            $order->save();
            return true;
        }catch (\Error|\Exception|\WC_Data_Exception $e){
            Logs::error( 'syncOrderStatus - order sync status error. ',
                [
                    'errorCode'     => esc_html($e->getCode()),
                    'errorMessage'  => esc_html($e->getMessage()),
                ]);
            $this->api()::wc_add_notice( esc_html($e->getMessage()), 'error' );
        }
        return false;
    }

    private function getMetaList($order, $meta_key): array
    {
        $meta_list = [];
        if ($order && method_exists($order, 'get_meta_data')) {
            $order_meta_data = $order->get_meta_data();
            foreach ($order_meta_data as $meta) {
                if ($meta->key === $meta_key) {
                    $meta_list[] = $meta->value;
                }
            }
        }
        return $meta_list;
    }

    /**
     * 同步订单退款状态
     * @param int    $order_id
     * @param string $refund_transaction_no
     * @return bool
     */
    private function syncOrderRefundStatus(int $order_id, string $refund_transaction_no): bool
    {
        try{
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }

            if ($order->get_payment_method() !== $this->id) {
                return false;
            }

            if ($order->get_meta('netsmax_gateway_for_woocommerce_merchant_no') !== $this->merchant_no) {

                return false;
            }

            if ($order->get_meta('netsmax_gateway_for_woocommerce_payment_status') !== 'SUCCEEDED') {
                return false;
            }

            if (empty($order->get_meta('netsmax_gateway_for_woocommerce_transaction_no'))) {
                return false;
            }

            if (empty($order->get_meta('netsmax_gateway_for_woocommerce_refund_status|' . $refund_transaction_no))) {
                return false;
            }
            // get list or string
            $getRTNO = $this->getMetaList($order, 'netsmax_gateway_for_woocommerce_refund_transaction_no');

            if(empty($getRTNO) || !in_array($refund_transaction_no, $getRTNO)) {
                return false;
            }
            /*
                    if ( in_array( $status , array('processing', 'completed', 'on-hold', 'cancelled' )) ) {
                        return true;
                    }*/

            $params = [
                'transaction_id'                => $order->get_id(),
                'transaction_no'                => $order->get_meta('netsmax_gateway_for_woocommerce_transaction_no'),
                'refund_transaction_no'         => $refund_transaction_no,
                'channel_refund_transaction_no' => $order->get_meta('netsmax_gateway_for_woocommerce_channel_refund_transaction_no|' . $refund_transaction_no),
            ];
            $request = $this->api()->apiOrderRefundQuery($params);
            $result  = $request['data'] ?? [];
            if (empty($result['refund_status'])
                || empty($result['transaction_id'])
                || empty($result['transaction_no'])
                || empty($result['channel_transaction_no'])
                || empty($result['refund_transaction_no'])
                || empty($result['channel_refund_transaction_no'])
                || empty($result['returnCode'])
                || empty($result['refund_amount'])
                || empty($result['refund_currency'])
            ) {
                return false;
            }

            $CRTNo = $order->get_meta('netsmax_gateway_for_woocommerce_channel_refund_transaction_no|' . $refund_transaction_no);
            if(empty($CRTNo)) {
                return false;
            }

            // $result['refund_amount'] fen
            $metaRefundAmount = $order->get_meta('netsmax_gateway_for_woocommerce_channel_refund_amount|' . $refund_transaction_no);
            if (empty($metaRefundAmount)
                || $metaRefundAmount <= 0
                || $metaRefundAmount * 100 != $result['refund_amount']
                || $order->get_currency() !== $result['refund_currency']
                || $order->get_meta('netsmax_gateway_for_woocommerce_transaction_no') !== $result['transaction_no']
            ) {
                return false;
            }
            $refund_id        = $result['channel_refund_transaction_no'];
            $refundStatusList = ['SUCCEEDED', 'FAILED', 'CANCELED', 'PROCESSING'];
            $refund_status    = strtoupper($result['refund_status']);
            if (!in_array($refund_status, $refundStatusList)) {
                return false;
            }

            if ($CRTNo === 'ping') {
                $order->update_meta_data('netsmax_gateway_for_woocommerce_channel_refund_transaction_no|' .
                    $refund_transaction_no, esc_html($refund_id));
            }

            if ($refund_status === $order->get_meta('netsmax_gateway_for_woocommerce_refund_status|' . $refund_transaction_no)) {
                //状态不变时不需要更新信息
                return true;
            }

            $reason = '';
            if (!empty($result['failMessage'])) {
                $reason = $result['failMessage'];
            }
            $order->update_meta_data('netsmax_gateway_for_woocommerce_refund_status', esc_html($refund_status));
            $order->update_meta_data('netsmax_gateway_for_woocommerce_refund_status|' . $refund_transaction_no, esc_html($refund_status));
            $refund_message = sprintf(
                // Translators: 1: is method title, 2: is refund status, 3: is reason, 4: is order transaction id
                esc_html__('Refund via %1$s. Refund status is %2$s. Reason: %3$s (Refund Reference: %4$s). ','netsmax-gateway-for-woocommerce'),
                esc_html($this->method_title),
                esc_html($refund_status),
                esc_html($reason == '' ? '-' : $reason),
                esc_html($refund_id)
            );
            $order->add_order_note($refund_message);
            $order->save();
            return true;
        }catch (\Error|\Exception|\WC_Data_Exception $e){
            Logs::error( 'syncOrderRefundStatus - order refund sync status error. ',
                [
                    'errorCode'     => esc_html($e->getCode()),
                    'errorMessage'  => esc_html($e->getMessage()),
                ]);
            $this->api()::wc_add_notice( esc_html($e->getMessage()), 'error' );
        }
        return false;
    }

    /**
     * 取消订单 时把已付款金额全额退回
     * @param $order_id
     * @return void
     * @throws \Exception
     */
    public function cancel_payment($order_id)
    {
        if(!$this->cancel_order_auto_gateway_refund) {
            return;
        }
        $order = wc_get_order($order_id);
        if ($order->get_transaction_id()
            && $this->id === $order->get_payment_method()
            && 'SUCCEEDED' === $order->get_meta('netsmax_gateway_for_woocommerce_payment_status')
        ) {
            $refund = $this->process_refund($order_id, $order->get_total(), esc_html__('cancel', 'netsmax-gateway-for-woocommerce'));
            if(!is_bool($refund)) {
                return $refund;
            }
        }
    }


    /**
     * Process a refund request from the Order details screen.
     * @param int        $order_id WC Order ID.
     * @param float|null $amount Refund Amount.
     * @param string     $reason Refund Reason
     * @return bool
     * @throws \Exception
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        if(!$this->refund_order_auto_gateway_refund) {
            Logs::debug('Process Refund Error: The option to synchronize refund information to Netsmax is not enabled');
            return true;
        }
        if(empty($amount) || $amount <= 0) {
            throw new \Exception( esc_html(__('Process Refund Error: Please enter the refund amount!', 'netsmax-gateway-for-woocommerce')) );
        }
        try{
            $result = $this->order_refund($order_id, $amount, $reason);
            if(is_bool($result)) {
                return (bool)$result;
            }
            Logs::debug('Process Refund Error: process refund failed!');
            if(is_wp_error($result) && method_exists($result, 'get_error_message')) {
                $resultMsg = (string)$result->get_error_message();
            }else{
                $resultMsg = (string)$result;
            }
            $errMsg = sprintf(
                // Translators: 1: is order id, 2: is method title, 3 is error reason
                esc_html__('Order [%1$s] refund and %2$s interaction failed! Reason for error:%3$s ', 'netsmax-gateway-for-woocommerce'),
                esc_html($order_id),
                esc_html($this->method_title),
                esc_html($resultMsg)
            );
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note(esc_html($errMsg), 1);
            }
            throw new \Exception( esc_html($resultMsg) );
            # return false;
        }catch (\Error|\Exception|\WC_Data_Exception $e){
            Logs::error( 'process_refund - order refund error. ',
                [
                    'errorCode'     => esc_html($e->getCode()),
                    'errorMessage'  => esc_html($e->getMessage()),
                ]);
            $this->api()::wc_add_notice( esc_html($e->getMessage()), 'error' );
            throw new \Exception( esc_html($e->getMessage()) );
        }
        // return false;
    }


    /**
     * 订单退款处理
     * @param int $order_id
     * @param float $amount
     * @param string $reason
     * @return string|true
     */
    private function order_refund($order_id, $amount, $reason = '')
    {
        if (!$this->merchant_no || !$this->merchant_key) {
            return __('payment settings not found', 'netsmax-gateway-for-woocommerce');
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return __('order not found', 'netsmax-gateway-for-woocommerce');
        }

        if ($this->id !== $order->get_payment_method() || empty($order->get_transaction_id())) {
            return __('The payment method has been changed', 'netsmax-gateway-for-woocommerce');
        }

        if ($order->get_meta('netsmax_gateway_for_woocommerce_merchant_no') !== $this->merchant_no) {
            return __('The MID has been changed', 'netsmax-gateway-for-woocommerce');
        }

        if ($order->get_meta('netsmax_gateway_for_woocommerce_payment_status') !== 'SUCCEEDED') {
            return __('Order not paid?', 'netsmax-gateway-for-woocommerce');
        }
        // netsmax_gateway_for_woocommerce_refund_status: 部分退款时为重复使用状态值
        $order_refund_status = $order->get_meta('netsmax_gateway_for_woocommerce_refund_status');
        if (!empty($order_refund_status) && !in_array( $order_refund_status, ['PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELED'])) {
            return __('Please process the existing refund before creating a new one.', 'netsmax-gateway-for-woocommerce');
        }

        if (empty($amount) || $amount <= 0) {
            $amount = $order->get_total();
        }

        if(empty($amount) || $amount <= 0 || $order->get_remaining_refund_amount() < 0) {
            return __('Error: Please enter the refund amount!', 'netsmax-gateway-for-woocommerce');
        }

        if ($amount > $order->get_total()) {
            return __('Can the refund amount not be greater than the order amount?', 'netsmax-gateway-for-woocommerce');
        }

        $params = [
            'transaction_id'         => $order->get_id(),
            'transaction_no'         => $order->get_meta('netsmax_gateway_for_woocommerce_transaction_no'),
            'channel_transaction_no' => $order->get_transaction_id(),
        ];
        $request = $this->api()->apiOrderQuery($params);
        $result  = $request['data'] ?? [];
        if (empty($result['returnCode'])
            || empty($result['payment_status'])
            || empty($result['transaction_id'])
            || empty($result['transaction_no'])
            || empty($result['channel_transaction_no'])
            || empty($result['amount'])
            || empty($result['currency'])
            || 'SUCCESS' !== $result['returnCode']
            || 'SUCCEEDED' !== $result['payment_status']
            || $order->get_total() * 100 != $result['amount']
            || strtoupper($order->get_currency()) !== $result['currency']
        ) {
            return __('Is this order information consistent with the information in Netsmax?', 'netsmax-gateway-for-woocommerce');
        }

        $refund_no = $this->order_refund_create_transaction_no($order);
        $order->add_meta_data('netsmax_gateway_for_woocommerce_refund_transaction_no', esc_html($refund_no), false);

        $refund_amount   = (int)bcmul($amount, 100);
        $refund_currency = strtoupper($order->get_currency());
        $merchant_note   = sprintf(
            // Translators: 1: is order transaction id, 2: is site url
            esc_html__('Refund for Order ID: #%1$s on %2$s', 'netsmax-gateway-for-woocommerce'),
            $order->get_id(),
            get_site_url()
        );

        $body = [
            'transaction_id'         => $order->get_id(),
            'transaction_no'         => $order->get_meta('netsmax_gateway_for_woocommerce_transaction_no'),
            'channel_transaction_no' => $order->get_transaction_id(),
            'refund_transaction_no'  => $refund_no,
            'refund_amount'          => $refund_amount,
            'refund_currency'        => $refund_currency,
            'refund_reason'          => $reason,
            'merchant_note'          => $merchant_note,
            'refund_notify_url'      => $this->webhook_url_order_refund_notify,
        ];
        $order->add_meta_data('netsmax_gateway_for_woocommerce_refund_status|' . $body['refund_transaction_no'], 'ping', true);
        $order->add_meta_data('netsmax_gateway_for_woocommerce_channel_refund_transaction_no|' . $body['refund_transaction_no'], 'ping', true);
        $order->add_meta_data('netsmax_gateway_for_woocommerce_channel_refund_amount|' . $body['refund_transaction_no'], $amount, true);
        $request = $this->api()->apiOrderRefund($body);
        $result  = $request['data'] ?? [];
        if (empty($request)
            || empty($result)
            || empty($result['returnCode'])
        ) {
            return sprintf(
                // Translators: 1: is method title,  2: refund status, 3: error reason
                esc_html__('Connection %1$s refund exception! code: %2$s, error: %3$s ', 'netsmax-gateway-for-woocommerce'),
                esc_html($this->method_title),
                esc_html($request['status'] ?? 500),
                esc_html($request['info'] ?? __('The server did not respond.', 'netsmax-gateway-for-woocommerce'))
            );
        }

        if ('SUCCESS' !== $result['returnCode']) {
            if (!empty($result['failCode'])) {
                return sprintf(
                    // Translators: 1: is refund fail code,  2: is error reason
                    esc_html__('Refund exception! code:%1$s,  error: %2$s ', 'netsmax-gateway-for-woocommerce'),
                    esc_html($result['failCode']),
                    esc_html($result['failMessage'] ?? '-')
                );
            } else {
                return __('Can&#39;t process refund at the moment. Try again later.', 'netsmax-gateway-for-woocommerce');
            }
        }

        if (empty($result['refund_status'])
            || empty($result['transaction_id'])
            || empty($result['transaction_no'])
            || empty($result['channel_transaction_no'])
            || empty($result['refund_transaction_no'])
            || empty($result['channel_refund_transaction_no'])
            || empty($result['refund_amount'])
            || empty($result['refund_currency'])
        ) {
            return sprintf(
                // Translators: 1: is method title,  2: is refund status, 3: is error reason
                esc_html__('Refund failed on %1$s! code: %2$s, error: %3$s ', 'netsmax-gateway-for-woocommerce'),
                esc_html($this->method_title),
                esc_html($result['status'] ?? 500),
                esc_html($result['info'] ?? __('The server did not respond.', 'netsmax-gateway-for-woocommerce'))
            );
        }
        if ($body['refund_transaction_no'] !== $result['refund_transaction_no']) {
            return sprintf(
                // Translators: 1: is method title
                esc_html__('Connection %1$s refund exception!', 'netsmax-gateway-for-woocommerce'),
                esc_html($this->method_title)
            );
        }
        if ((int)$body['refund_amount'] !== (int)$result['refund_amount']) {
            return sprintf(
                // Translators: 1: is method title
                esc_html__('Refund exception! Please log in to the %1$s merchant system to confirm the refund order information.', 'netsmax-gateway-for-woocommerce'),
                esc_html($this->method_title)
            );
        }

        $order->update_meta_data('netsmax_gateway_for_woocommerce_channel_refund_transaction_no|' . $body['refund_transaction_no'], esc_html($result['channel_refund_transaction_no']));
        $order->update_meta_data('netsmax_gateway_for_woocommerce_refund_status|' . $body['refund_transaction_no'], esc_html($result['refund_status']));
        $order->update_meta_data('netsmax_gateway_for_woocommerce_refund_status', esc_html($result['refund_status']));

        $amount         = wc_price($amount, ['currency' => $result['refund_currency']]);
        $refund_id      = $result['channel_refund_transaction_no'];
        $refund_message = sprintf(
            // Translators: 1: is method title, 2: is refund amount, 3: is refund status, 4: is error reason, 5: is refund no
            esc_html__('Refund via %1$s %2$s. Refund status is %3$s. Reason: %4$s (Refund Reference: %5$s). ','netsmax-gateway-for-woocommerce'),
            esc_html($this->method_title),
            $amount,
            esc_html(strtoupper($result['refund_status'])),
            esc_html($reason == '' ? '-' : $reason),
            esc_html($refund_id)
        );
        $order->add_order_note($refund_message);
        $order->save();
        return true;
    }

    /**
     * 退款异步通知处理流
     * @return null
     */
    public function process_webhook_order_refund_notify_netsmax()
    {
        try{
            $response = $this->api()->apiGetRefundNotify();
            if (empty($response)) {
                return $this->responseExit(403, '');
            }
            if (!is_array($response)) {
                return $this->responseExit(403, $response);
            }
            $result = $response;
            if (empty($result['returnCode']) || 'SUCCESS' !== $result['returnCode']) {
                return $this->responseExit(403, 'Return Code Error.');
            }

            if (empty($result['transaction_id'])
                || empty($result['transaction_no'])
                || empty($result['channel_transaction_no'])
                || empty($result['refund_transaction_no'])
                || empty($result['channel_refund_transaction_no'])
            ) {
                return $this->responseExit(403, 'Transaction No Error.');
            }
            $refundStatus = ['PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELED'];
            if (empty($result['refund_status']) || !in_array($result['refund_status'], $refundStatus)) {
                return $this->responseExit(403, 'Refund Status Error.');
            }

            if ('SUCCEEDED' === $result['refund_status']) {
                $order = wc_get_order($result['transaction_id']);
                if (!$order) {
                    return $this->responseExit(404, 'Order Is Null.');
                }
                if ($order->get_meta('netsmax_gateway_for_woocommerce_refund_status|' . $result['refund_transaction_no']) === $result['refund_status']) {
                    // success
                    return $this->responseExit(200, 'SUCCESS');
                }
            }

            if ($this->syncOrderRefundStatus($result['transaction_id'], $result['refund_transaction_no'])) {
                // success
                return $this->responseExit(200, 'SUCCESS');
            }
        }catch (\WC_Data_Exception|\Exception|\Error $e){
            Logs::error( 'process_webhook_order_refund_notify_netsmax - order refund notify failed. ',
                [
                    'errorCode'     => esc_html($e->getCode()),
                    'errorMessage'  => esc_html($e->getMessage()),
                ]);
            $this->api()::wc_add_notice( esc_html($e->getMessage()), 'error' );
            return $this->responseExit(500,
                sprintf(
                    // Translators: 1: is method title
                    esc_html__( '%1$s order refund notification processing error', 'netsmax-gateway-for-woocommerce' ),
                    esc_html($this->method_title)
                )
            );
        }

        return $this->responseExit(201, 'FAILED');
    }

    /**
     * create order transaction no
     * @param $order
     * @return string
     */
    private function order_create_transaction_no($order):string {
        return 'WCT' . $this->merchant_no . 'I' . $order->get_id() . 'I' . Request::getTimeMs();
    }

    /**
     * create refund transaction no
     * @param $order
     * @return string
     */
    private function order_refund_create_transaction_no($order):string {
        return 'WCR' . $order->get_meta('netsmax_gateway_for_woocommerce_merchant_no')
            . 'I' . $order->get_id() . 'I' . Request::getTimeMs();
    }

    private function getOrderStatusInfo(string $status = '') {
        $statusInfo = [
            'PROCESSING' => __('PROCESSING', 'netsmax-gateway-for-woocommerce'),
            'SUCCEEDED'  => __('SUCCEEDED', 'netsmax-gateway-for-woocommerce'),
            'FAILED'     => __('FAILED', 'netsmax-gateway-for-woocommerce'),
            'CANCELED'   => __('CANCELED', 'netsmax-gateway-for-woocommerce'),
            'EXPIRED'    => __('EXPIRED', 'netsmax-gateway-for-woocommerce'),
        ];
        if(empty($status)) {
            return $statusInfo;
        }
        return $statusInfo[$status] ?? '-';
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        if($this->description) {
            echo '<p>' . wp_kses_post( $this->description ) . '</p>';
        }
        if ( $this->supports( 'tokenization' ) && is_checkout() &&  is_user_logged_in() ) {
            $this->tokenization_script();
            $this->saved_payment_methods();
            $this->save_payment_method_checkbox();
        }
    }


    /**
     * Show new card can only be added when placing an order notice.
     */
    public function add_payment_method()
    {
       // Logs::debug('add_payment_method - log');
        wc_add_notice(esc_html__('You can only use the new card when placing an order.', 'netsmax-gateway-for-woocommerce'), 'error');
        return;
    }

    /**
     * Verify payment.
     * 验证支付订单合法性。
     */
    public function verify_transaction()
    {

        Logs::debug('verify_transaction - Verify the legality of payment orders', [
            'post' => wp_json_encode(wc_clean( wp_unslash( $this->get_post_data() ) ) ),
            'get'  => wp_json_encode(wc_clean( wp_unslash($_GET ?? [] ) ) ),
        ]);
    }

    /**
     * 同步跳转
     * @return void
     */
    public function process_webhooks_order_callback()
    {
        Logs::debug('process_webhooks_order_callback - Synchronize jump', [
            'post' => wp_json_encode(wc_clean( wp_unslash( $this->get_post_data() ) ) ),
            'get'  => wp_json_encode(wc_clean( wp_unslash($_GET ?? [] ) ) ),
        ]);
    }

    /**
     * 订单状态发生更改时，触发钩子
     * @param int      $order_id
     * @param string   $old_status
     * @param string   $new_status
     * @param \WC_Order $order
     * @return void
     */
    public function hook_order_status_changed(int $order_id, string $old_status, string $new_status, \WC_Order $order)
    {
        if ($order->get_payment_method() != $this->id) {
            return;
        }
        // 订单处理...
        // 日志监听操作...
        Logs::debug('hook_order_status_changed - Trigger hook when order status changes', [
            'order_id'   => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ]);
    }

    /**
     * 检测商户权限
     * @return void
     */
    public function process_webhook_merchant_verification_payment_page_netsmax() {
        Logs::debug('process_webhook_merchant_verification_payment_page_netsmax - verification payment page', [
            'post' => wp_json_encode(wc_clean( wp_unslash( $this->get_post_data() ) ) ),
            'get'  => wp_json_encode(wc_clean( wp_unslash($_GET ?? [] ) ) ),
        ]);
        $this->responseExit(200, 'OK');
    }

    public function task_order_status_sync_callback() {
        if(!is_admin()) {
            // $this->responseExit(403, 'Permission denied');
            return;
        }
        return $this->process_webhook_sync_order_status_netsmax($this->merchant_no);
    }
    /**
     * 同步未付款成功订单的状态；
     * @return void
     */
    public function process_webhook_sync_order_status_netsmax(string $mid = '') {

        if($this->enabled !== 'yes') {
            return $this->responseMsg(403, 'Plugin not enabled');
        }

        // The parameters received this time are only for comparison purposes, to prevent excessive load caused by direct URL requests
        $merchantNo = wc_clean( wp_unslash( $_GET['mid'] ?? $mid ) );

        if(empty($merchantNo) || $merchantNo !== $this->merchant_no ) {
            return $this->responseMsg(403, 'Permission denied');
        }
        $pageNext = 1;
        $syncStat = [
            'count'   => 0,
            'success' => 0,
            'fail'    => 0,
        ];
        $syncInfo = [];
        do{
            $list = $this->get_pending_orders($this->merchant_no, $pageNext);
            if(!empty($list)) {
                $syncStat['count'] += count($list);
                foreach ($list as $_id) {
                    $syncUp = false;
                    try{
                        $syncUp = $this->syncOrderStatus($_id);
                    }catch (\Error|\Exception $e) {
                        Logs::error('process_webhook_sync_order_status_netsmax: sync order status error!', [
                            'code'    => esc_html($e->getCode()),
                            'message' => esc_html($e->getMessage()),
                        ]);
                    }
                    if(true === $syncUp) {
                        $syncStat['success']++;
                        $syncInfo[] = [
                            'status'   => 'success',
                            'order_id' => $_id,
                            'syncInfo' => 'true',
                        ];
                    }else{
                        $syncStat['fail']++;
                        $syncInfo[] = [
                            'status'   => 'fail',
                            'order_id' => $_id,
                            'syncInfo' => 'false',
                        ];
                    }

                }
            }
            if(!empty($list)) {
                $pageNext++;
            }else{
                $pageNext = 0;
            }
        }while($pageNext > 0);
        Logs::debug('process_webhook_sync_order_status_netsmax: Sync Order Payment Status.', $syncInfo);
        return $this->responseMsg(200,
            sprintf(
                // Translators: 1: is order quantity, 2: is order there are updates available, 3: is order quantity not updated
                esc_html__('Status of synchronized order payment: There are a total of %1$s orders that need to be synchronized, %2$s orders have been synchronized, and %3$s orders have failed to synchronize.', 'netsmax-gateway-for-woocommerce'),
                (int)$syncStat['count'],
                (int)$syncStat['success'],
                (int)$syncStat['fail'],
            )
        );
    }



    protected function get_pending_orders(string $merchant_no, int $page = 1 ) {

        if(empty($merchant_no) || empty($page)) {
            return [];
        }

        $order_query = [
            'orderby'        => 'id',
            'order'          => 'ASC',
            'date_query'     => [
                'after'  => gmdate('Y-m-d H:i:s', strtotime('-12 hours')), // 获取12小时之内产生的订单
                # 'before' => gmdate('Y-m-d H:i:s'),

            ],
            'payment_method' => [$this->id],
            'status'         => ['pending', 'on-hold'],
            'limit'          => 100,
            'page'           => $page,
            'meta_query'     => [
                [
                    'key'     => 'netsmax_gateway_for_woocommerce_merchant_no',
                    'value'   => $merchant_no,
                    'compare' => '='
                ]
            ],
            'return'         => 'ids', // return order ID
        ];
        try{
            return wc_get_orders( $order_query );
        }catch (\Exception|\Error $e){
            //
        }
        return [];
    }
}