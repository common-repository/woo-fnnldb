<?php
/**
 * Plugin Name: WooCommerce FnnlDb
 * Plugin URI: https://wordpress.org/plugins/woo-fnnldb/
 * Description: Take credit card payments on your store using FnnlDb.
 * Author: FnnlDb
 * Author URI: https://fnnldb.com
 * Version: 1.0.0
 * Requires at least: 4.4
 * Tested up to: 5.2
 * WC requires at least: 2.6
 * WC tested up to: 3.6
 * Text Domain: woocommerce-fnnldb
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function woocommerce_fnnldb_missing_wc_notice() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'FnnlDb requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-fnnldb' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

add_action( 'plugins_loaded', 'woocommerce_fnnldb_init' );

function woocommerce_fnnldb_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_fnnldb_missing_wc_notice' );
		return;
	}

	if ( ! class_exists( 'WC_FnnlDb' ) ) :
		define( 'WC_FNNLDB_VERSION', '1.0.0' );
		define( 'WC_FNNLDB_MIN_PHP_VER', '5.6.0' );
		define( 'WC_FNNLDB_MIN_WC_VER', '2.6.0' );
		define( 'WC_FNNLDB_MAIN_FILE', __FILE__ );
		define( 'WC_FNNLDB_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_FNNLDB_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		class WC_FnnlDb extends WC_Payment_Gateway {

			private static $instance;

			private $url = 'https://api.fnnldb.com/v1/';

			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			public function __construct() {
				$this->id = 'fnnldb';
				$this->has_fields = true;
				$this->method_title = 'FnnlDb';
				$this->method_description = 'Take credit card payments on your store using FnnlDb.';
				$this->supports = ['products', 'refunds'];

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->get_option('title');
        		$this->description = $this->get_option('description');
        		$this->api_key = $this->get_option('api_key');
        		$this->funnel_id = $this->get_option('funnel_id');

				$this->init();
			}

			public function init() {
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action('woocommerce_product_options_related', [$this, 'add_product_options']);
				add_action('woocommerce_process_product_meta', [$this, 'save_product_options']);
			}

			public function update_plugin_version() {
				delete_option( 'wc_fnnldb_version' );
				update_option( 'wc_fnnldb_version', WC_FNNLDB_VERSION );
			}

			public function install() {
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}

				if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_FNNLDB_VERSION !== get_option( 'wc_fnnldb_version' ) ) ) {
					do_action( 'woocommerce_fnnldb_updated' );

					if ( ! defined( 'WC_FNNLDB_INSTALLING' ) ) {
						define( 'WC_FNNLDB_INSTALLING', true );
					}

					$this->update_plugin_version();
				}
			}

			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="admin.php?page=wc-settings&tab=checkout&section=fnnldb">' . esc_html__( 'Settings', 'woocommerce-fnnldb' ) . '</a>',
					'<a href="https://fnnldb.com/docs" target="_blank">' . esc_html__( 'Docs', 'woocommerce-fnnldb' ) . '</a>',
					'<a href="https://woocommerce.com/contact-us/" target="_blank">' . esc_html__( 'Support', 'woocommerce-fnnldb' ) . '</a>',
				);
				return array_merge( $plugin_links, $links );
			}

			public function add_gateways( $methods ) {
				$methods[] = 'WC_FnnlDb';
				return $methods;
			}

			public function init_form_fields() {
				$this->form_fields = [
				    'enabled' => [
				        'title' => __( 'Enable/Disable', 'woocommerce-fnnldb' ),
				        'type' => 'checkbox',
				        'label' => __( 'Enable FnnlDb', 'woocommerce-fnnldb' ),
				        'default' => 'yes'
				    ],
				    'title' => [
				        'title' => __( 'Title', 'woocommerce-fnnldb' ),
				        'type' => 'text',
				        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-fnnldb' ),
				        'default' => __( 'Credit Card', 'woocommerce-fnnldb' ),
				        'desc_tip'      => true,
				    ],
				    'description' => [
				        'title' => __( 'Description', 'woocommerce-fnnldb' ),
				        'type' => 'text',
				        'default' => 'Pay with a Credit Card'
				    ],
				    'apisettings' => [
						'title'       => __( 'API Settings', 'woocommerce-fnnldb' ),
						'type'        => 'title',
						'description' => '',
					],
				    'api_key' => [
				    	'title' => __( 'API Key', 'woocommerce-fnnldb' ),
				    	'type' => 'text',
				    	'default' => ''
				    ],
				    'funnel_id' => [
				    	'title' => __( 'Funnel ID', 'woocommerce-fnnldb' ),
				    	'type' => 'text',
				    	'default' => ''
				    ]
				];
			}

			public static function add_product_options() {
	            \woocommerce_wp_text_input([
	                'id'          => 'fnnldb_product_id',
	                'label'       => 'Funnel Product ID',
	                'desc_tip'    => true,
	                'description' => 'This is the product id located in the products tab of the funnel settings page.',
	            ]);
		    }

		    public static function save_product_options( $post_id ) {
		    	\update_post_meta(
                    $post_id,
                    'fnnldb_product_id',
                    esc_attr($_POST['fnnldb_product_id'])
            	);
		    }

			public function payment_fields() {
				include_once __DIR__ . '/templates/payment_fields.php';
			}

			public function get_transaction_url( $order ) {
				$this->view_transaction_url = 'https://fnnldb.com/database/ecommerce/orders/%s';
				return parent::get_transaction_url( $order );
			}

			public function process_payment( $order_id ) {
			    global $woocommerce;
			    $order = new WC_Order( $order_id );

			    // payment info
			    $card_number = esc_attr($_POST['card_number']);
			    $card_exp = explode('/', esc_attr($_POST['card_exp']));
			    $card_cvv = esc_attr($_POST['card_cvv']);

			    $payload = [
			    	'key' => $this->get_option('api_key'),
			    	'funnel_id' => $this->get_option('funnel_id'),
			    	'first_name' => $order->get_billing_first_name(),
			    	'last_name' => $order->get_billing_last_name(),
			    	'company_name' => $order->get_billing_company(),
			    	'email' => $order->get_billing_email(),
			    	'phone' => $order->get_billing_phone(),
			    	'address' => $order->get_billing_address_1(),
			    	'address2' => $order->get_billing_address_2(),
			    	'city' => $order->get_billing_city(),
			    	'state' => $order->get_billing_state(),
			    	'zipcode' => $order->get_billing_postcode(),
			    	'country' => $order->get_billing_country(),
			    	'shipping_first_name' => ($order->has_shipping_address()) ? $order->get_shipping_first_name() : $order->get_billing_first_name(),
			    	'shipping_last_name' => ($order->has_shipping_address()) ? $order->get_shipping_last_name() : $order->get_billing_last_name(),
			    	'shipping_company_name' => ($order->has_shipping_address()) ? $order->get_shipping_company() : $order->get_billing_company(),
			    	'shipping_address' => ($order->has_shipping_address()) ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
			    	'shipping_address2' => ($order->has_shipping_address()) ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
			    	'shipping_city' => ($order->has_shipping_address()) ? $order->get_shipping_city() : $order->get_billing_city(),
			    	'shipping_state' => ($order->has_shipping_address()) ? $order->get_shipping_state() : $order->get_billing_state(),
			    	'shipping_zipcode' => ($order->has_shipping_address()) ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
			    	'shipping_country' => ($order->has_shipping_address()) ? $order->get_shipping_country() : $order->get_billing_country(),
            		'ip_address'  => $order->get_customer_ip_address(),
            		'payment_source' => 'card',
            		'card_number' => $card_number,
            		'card_exp_month' => $card_exp[0],
            		'card_exp_year' => $card_exp[1],
            		'card_cvv' => $card_cvv
            	];

            	$cart_items = $order->get_items();
				$counter = 0;

				foreach ( $cart_items as $cart_item ) {
					$payload['products'][$counter] = [
						'id' => get_post_meta( $cart_item['product_id'], 'fnnldb_product_id', true ),
						'price' => $order->get_item_total( $cart_item, false, false ),
						'qty' => $cart_item['qty']
					];

					$counter ++;
				}

	            $response = wp_remote_post( $this->url.'orders/create' , $payload);

	            if (is_wp_error( $response )) {
	                wc_add_notice( __('Payment error:', 'woothemes') . $response->get_error_message(), 'error' );
					return;
	            }
	            $response = json_decode($content);

		        if($response->status == 'error') {
		        	$errors = '';
		        	foreach($response->data->errors as $error) {
		        		$errors .= $error[0] . ' ';
		        	}
			    	wc_add_notice( __('Payment error:', 'woothemes') . trim($errors), 'error' );
					return;
				} elseif($response->status == 'success') {
					$this->send_order_confirmation($response->data->id);
					$order->update_meta_data('fnnldb_order_id', $response->data->id);
			    	$order->payment_complete( $response->data->id );
			    	$order->reduce_order_stock();
			    	$woocommerce->cart->empty_cart();
				    return [
				        'result' => 'success',
				        'redirect' => $this->get_return_url( $order )
				    ];
				}
			}

			public function send_order_confirmation( $orderId ) {
				$payload = [
					'key' => $this->get_option('api_key'),
			    	'funnel_id' => $this->get_option('funnel_id'),
			    	'order_id' => $orderId
				];

				$response = wp_remote_post( $this->url.'orders/confirm' , $payload);

	            if (is_wp_error( $response )) {
	                wc_add_notice( __('Payment error:', 'woothemes') . $response->get_error_message(), 'error' );
					return;
	            }
		       	$response = json_decode($content);
		       	return $response;
			}

			public function can_refund_order( $order ) {
				$has_api_creds = $this->get_option( 'api_key' ) && $this->get_option( 'funnel_id' );
				return $order && $order->get_transaction_id() && $has_api_creds;
			}

			public function process_refund( $order_id, $amount = null, $reason = '' ) {
				$order = wc_get_order( $order_id );

				if ( ! $this->can_refund_order( $order ) ) {
					return new WP_Error( 'error', __( 'Refund failed.', 'woocommerce-fnnldb' ) );
				}

				$payload = [
					'key' => $this->get_option('api_key'),
			    	'order_id' => get_post_meta( $order_id, 'fnnldb_order_id', true ),
			    	'amount' => ($amount) ? esc_attr($amount) : $order->get_total(),
			    	'reason' => esc_attr($reason)
				];

				$response = wp_remote_post( $this->url.'orders/refund' , $payload);

	            if (is_wp_error( $response )) {
	                return new WP_Error( 'error', $response->get_error_message() );
	            }
		       	$response = json_decode($content);

		       	if($response->status == 'error') {
		       		$errors = '';
		       		foreach($response->data->errors as $error) {
		       			$errors .= $error[0] . ' ';
		       		}
		       		return new WP_Error( 'error', trim($errors) );
		       	} elseif($response->status == 'success') {
		       		$order->add_order_note( __( 'Refunded $'.number_format(esc_attr($response->data->refunded_amount), 2, '.', ','), 'woocommerce-fnnldb' ) );
					return true;
		       	}
			}
		}

		WC_FnnlDb::get_instance();
	endif;
}
