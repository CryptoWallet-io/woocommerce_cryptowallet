<?php
/*
Plugin Name: WooCommerce CryptoWallet Card Payment Gateway
Description: CryptoWallet.io payment gateway plugin for WooCommerce via Direct Method. Server to Server authorization
Version: 1.0.0
Author: CryptoWallet.io
Author URI: CryptoWallet.io
*/

add_action( 'plugins_loaded', 'woocommerce_cryptowallet_card_init', 0 );

function woocommerce_cryptowallet_card_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/**
	 * Gateway Class
	 */
	class WC_Gateway_CryptoWallet_Card extends WC_Payment_Gateway {

		/**
		 *Define CryptoWallet_Card Variables
		 */

		public $api_key;

		/**
		 * Constructor
		 */
		function __construct() {

			$this->id 				= 'cryptowallet_card';
			$this->method_title		= __('CryptoWallet Card', 'cryptowallet');
			$this->has_fields 		= true;

			// Load the form fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Get setting values
			$this->title 					= $this->settings['title'];
			$this->description 	  = $this->settings['description'];
			$this->enabled			  = $this->settings['enabled'];
			$this->api_key		    = $this->settings['api_key'];
			$this->uid		    		= $this->settings['uid'];
			$this->testmode			  = $this->settings['testmode'];

			// SSL check hook used on admin options to determine if SSL is enabled
			add_action( 'admin_notices', array( &$this, 'ssl_check' ) );

			// Save admin options
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}

			add_action('woocommerce_receipt_cryptowallet_card', array(&$this, 'receipt_page'));
			add_action('woocommerce_thankyou_cryptowallet_card',array(&$this, 'thankyou_page'));
		}

		/**
		 * Check if SSL is enabled and notify the user if SSL is not enabled
		 */
		function ssl_check() {

			if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
				echo '<div class="error"><p>'.sprintf(__('CryptoWallet Card is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate', 'cryptowallet'), admin_url('admin.php?page=woocommerce')).'</p></div>';
			}
		}

		/**
		 *Initialize Gateway Settings Form Fields
		 */
		function init_form_fields() {

			$this->form_fields = array(
				'title' => array(
					'title' => __( 'Title', 'cryptowallet' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'cryptowallet' ),
					'default' => __( 'Credit Card / Debit Card', 'cryptowallet' )
				),
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'cryptowallet' ),
					'label' => __( 'Enable CryptoWallet Card', 'cryptowallet' ),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'description' => array(
					'title' => __( 'Description', 'cryptowallet' ),
					'type' => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'cryptowallet' ),
					'default' => 'Pay with your creditor deb it card.'
				),
				'api_key' => array(
					'title' => __( 'API Key', 'cryptowallet' ),
					'type' => 'text',
					'description' => __( 'API Key provided by CryptoWallet.io', 'cryptowallet' ),
					'default' => ''
				),
				'uid' => array(
					'title' => __( 'User ID', 'cryptowallet' ),
					'type' => 'text',
					'description' => __( 'User ID provided by CryptoWallet.io', 'cryptowallet' ),
					'default' => ''
				),
				'testmode' => array(
					'title' => __( 'Test Mode',  'cryptowallet' ),
					'label' => __( 'Enable Test Mode', 'cryptowallet' ),
					'type' => 'checkbox',
					'description' => __( 'Process transactions in Test Mode',  'cryptowallet' ),
					'default' => 'no'
				),
			);
		}

		/**
		 * Admin panel options
		 */
		function admin_options() {

			?>
			<h3><?php _e( 'CryptoWallet Card', 'cryptowallet' ); ?></h3>
			<p><?php _e( 'CryptoWallet Card works by adding credit card fields on the checkout and then sending the details to CryptoWallet Card for authorization.', 'cryptowallet' ); ?></p>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table>
			<?php
		}

		/**
		 * Check if this gateway is enabled and available in the user's country
		 */
		function is_available() {

			if ($this->enabled=="yes") {

				if (get_option( 'woocommerce_force_ssl_checkout' ) == 'no' ) {
					//	return false;
				}
				return true;
			} else {
				return false;
			}
		}

		/*
		 * Payment form on checkout page
		 */
		function payment_fields() {

			?>
			<div>

			<span> <h2>Confirm Payment Details</h2>
      </span>
			</div>

			<?php if ( $this->description ) : ?>
				<p><?php echo $this->description; ?></p>
			<?php endif; ?>

			<fieldset>
				<p class="form-row form-row-first">
					<label for="cryptowallet_card_card_owner_title><?php echo __( 'Card Holder Title', 'woocommerce' ) ?> <span class="required" style="display: inline;">*</span></label>
					<input type="text" class="input-text" name="cryptowallet_card_card_owner_title" />
				</p>

				<p class="form-row form-row-first">
					<label for="cryptowallet_card_card_owner"><?php echo __( 'Card Holder', 'woocommerce' ) ?> <span class="required" style="display: inline;">*</span></label>
					<input type="text" class="input-text" name="cryptowallet_card_card_owner" />
				</p>
				<p class="form-row form-row-first">
					<label for="cryptowallet_card_card_number"><?php echo __( 'Credit Card number', 'woocommerce' ) ?> <span class="required" style="display: inline;">*</span></label>
					<input type="text" onkeypress="return isNumberKey(event)" onkeyup="cardlogo(this.value)"  class="input-text" name="cryptowallet_card_card_number" maxlength="19"/>
					<span id="card_icon"></span>
					<span id="card_error"></span>
				</p>

				<div class="clear"></div>
				<p>
					<label for="cc-expire-month"><?php echo __( 'Expiration date', 'woocommerce' ) ?> <span class="required" style="display: inline;">*</span></label>
					<select name="cryptowallet_card_card_expiration_month" id="cc-expire-month">
						<option value=""><?php _e( 'Month', 'woocommerce' ) ?></option>
						<?php
						$months = array();
						for ( $i = 1; $i <= 12; $i++ ) {
							$timestamp = mktime( 0, 0, 0, $i, 1 );
							$months[ date( 'm', $timestamp ) ] = date( 'F', $timestamp );
						}
						foreach ( $months as $num => $name ) {
							printf( '<option value="%s">%s</option>', $num, $name );
						}
						?>
					</select>
					<select name="cryptowallet_card_card_expiration_year" id="cc-expire-year">
						<option value=""><?php _e( 'Year', 'woocommerce' ) ?></option>
						<?php
						$years = array();
						for ( $i = date( 'Y' ); $i <= date( 'Y' ) + 15; $i++ ) {
							printf( '<option value="%u">%u</option>', $i, $i );
						}
						?>
					</select>
				</p>
				<p>
					<label for="cryptowallet_card_card_csc"><?php _e( 'Card Security Code', 'woocommerce' ); ?> <span class="required" style="display: inline;">*</span></label>
					<input type="text" onkeypress="return isNumberKey(event)"   class="input-text" id="cryptowallet_card_card_csc" name="cryptowallet_card_card_csc" maxlength="4" style="width:45px" />
					<br />
					<span id="cryptowallet_card_card_csc_description">3 digits usually found on the back of the card.</span>
				</p>
				<div class="clear"></div>
				<input type="hidden" name="cryptowallet_card_card_type" id="card_type" />
			</fieldset>
			<script type="text/javascript">
				function isNumberKey(evt)
				{
					var charCode = (evt.which) ? evt.which : event.keyCode
					if (charCode > 31 && (charCode < 48 || charCode > 57))
						return false;

					return true;
				}
			</script>
			<script type="text/javascript">
				function cardlogo(number)
				{


					var cardtype = GetCardType(number);

					if( cardtype == "0")
					{
						jQuery("#notsup").html();
						try { jQuery("#card_icon").removeClass('cc_icon_'+cardtype); } catch(e) { }
						try { jQuery("#card_error").html('Card not supported'); } catch(e) { }
						jQuery("#card_type").val("");

					}
					else {
						try { jQuery("#card_icon").addClass('cc_icon_'+cardtype); } catch(e) { }
						jQuery("#card_type").val(cardtype);
					}
				}


				function GetCardType(number)
				{
					// visa
					var re = new RegExp("^4");
					if (number.match(re) != null)
						return "visa";

					// Mastercard
					re = new RegExp("^5[1-5]");
					if (number.match(re) != null)
						return "mc";

					// AMEX
					re = new RegExp("^3[47]");
					if (number.match(re) != null)
						return "amex";

					// Discover
					re = new RegExp("^(6011|622(12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5]|64[4-9])|65)");
					if (number.match(re) != null)
						return "discover";

					// Diners
					re = new RegExp("^36");
					if (number.match(re) != null)
						return "diners";

					// Diners - Carte Blanche
					re = new RegExp("^30[0-5]");
					if (number.match(re) != null)
						return "diners";

					// JCB
					re = new RegExp("^35(2[89]|[3-8][0-9])");
					if (number.match(re) != null)
						return "jcb";

					// Visa Electron
					re = new RegExp("^(4026|417500|4508|4844|491(3|7))");
					if (number.match(re) != null)
						return "visa";

					return "0";
				}
			</script>
			<style>
				.cc_icon_diners { background: url( <?php echo plugins_url('assets/diners.jpg',__FILE__);?>); width:36px; height: 24px  }
				.cc_icon_visa { background: url( <?php echo plugins_url('assets/visa.svg', __FILE__ );?>); width:36px; height: 24px  }
				.cc_icon_mc { background: url( <?php echo plugins_url('assets/mc.svg' , __FILE__ );?>); width:36px; height: 24px  }
				.cc_icon_amex{ background: url( <?php echo plugins_url('assets/amex.svg', __FILE__);?>); width:36px; height: 24px  }
			</style>
			<?php
		}

		/**
		 * Process the payment, receive and validate the results, and redirect to the thank you page upon a successful transaction
		 */
		function process_payment( $order_id ) {


			$order = new WC_Order( $order_id );

			$card_number		= isset( $_POST['cryptowallet_card_card_number'] ) ? $_POST['cryptowallet_card_card_number'] : '';
			$card_csc			= isset( $_POST['cryptowallet_card_card_csc'] ) ? $_POST['cryptowallet_card_card_csc'] : '';
			$card_exp_month		= isset( $_POST['cryptowallet_card_card_expiration_month'] ) ? $_POST['cryptowallet_card_card_expiration_month'] : '';
			$card_exp_year		= isset( $_POST['cryptowallet_card_card_expiration_year'] ) ? $_POST['cryptowallet_card_card_expiration_year'] : '';
			$card_type			= isset( $_POST['cryptowallet_card_card_type'] ) ? $_POST['cryptowallet_card_card_type'] : '';
			$card_owner			= isset( $_POST['cryptowallet_card_card_owner'] ) ? $_POST['cryptowallet_card_card_owner'] : '';
			$card_owner_title	= isset( $_POST['cryptowallet_card_card_owner_title'] ) ? $_POST['cryptowallet_card_card_owner_title'] : '';

			// Format credit card number
			$card_number = str_replace( array( ' ', '-' ), '', $card_number );

			// Validate plugin settings
			if ( ! $this->validate_settings() ) {
				$cancelNote = __('Order was cancelled due to invalid settings (check your credentials).', 'woothemes');
				$order->add_order_note( $cancelNote );
				wc_add_notice(__('Payment was rejected due to configuration error.', 'cryptowallet'), 'error' );
				return false;
			}

			$url = 'https://cryptowallet.io/api/v1/card/charge/'.$this->uid;

			list( $st1, $st2 )  = split(" ",$order->billing_address_1);
			$st2						   .= $order->billing_address_2;

			$payload = [
				'addresses' => [
					'billing' => [
						'name_number'	=> $st1 ,
						'first_line'	=> $st2 ,
						'town_city'		=> $order->billing_city,
						'state_county'	=> $order->billing_state ,
						'post_zip'		=> $order->billing_postcode,
						'country'		=> $order->billing_country
					]
				],
				'card'		  		=> [
					'title'			=> $card_owner_title,
					'card_holder' 	=> $card_owner,
					'long_num'   	=> $card_number,
					'exp_month' 	=> $card_exp_month,
					'exp_year'		=> $card_exp_year,
					'ccv'			=> $card_csc,
					'type'			=> $card_type
				],
				'customer' => [
					'email'     => $order->billing_email ,
					'firstname' => $order->billing_first_name ,
					'lastname'	=> $order->billing_last_name
				],

				'transaction' => [
					'amount' => $order->order_total
				]
			];


			$header = [
				'X-Authorization: '.$this->api_key , 'Content-Type: application/json'
			];

			if( $this->testmode ) {
				$header[] = 'ENV: test';
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header );
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true );
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true );
			curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');
			curl_setopt($ch, CURLOPT_POSTFIELDS,  json_encode($payload));

			$response = curl_exec ($ch);

			//print_r(curl_getinfo($ch));

			curl_close($ch);
			//print $response;

			$json   = json_decode($response,1);
			//print_r($payload); print_r($json);

			//The first element of the retrn array ($arrResults[0]) is the Result. 0=Successful, 1=Warning (A result of 1 is returned either when the fraud module is providing a flag or if unnecessary parameters were sent to the API in the request message).
			if ( $json['code'] == 200 )
			{
				$order->add_order_note( __( 'CryptoWallet_Card payment completed', 'cryptowallet' )  );
				$order->payment_complete();
				WC()->cart->empty_cart();

				//redirect to the woocommerce thank you page
				return array(
					'result' => 'success',
					'redirect' => add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order_id, get_permalink( get_option( 'woocommerce_thanks_page_id' ) ) ) )
				);

			}
			else {
				if(!$json['message']){
					$json['message'] = __('Gateway Error' , 'cryptowaller');
				}
				wc_add_notice( $json['message']  , 'error' );
			}

			return true;
		}

		/**
		 * Validate the payment form prior to submitting via wp_remote_posts
		 */
		function validate_fields() {

			$card_number		  = isset( $_POST['cryptowallet_card_card_number'] ) ? $_POST['cryptowallet_card_card_number'] : '';
			$card_csc				  = isset( $_POST['cryptowallet_card_card_csc'] ) ? $_POST['cryptowallet_card_card_csc'] : '';
			$card_exp_month		= isset( $_POST['cryptowallet_card_card_expiration_month'] ) ? $_POST['cryptowallet_card_card_expiration_month'] : '';
			$card_exp_year		= isset( $_POST['cryptowallet_card_card_expiration_year'] ) ? $_POST['cryptowallet_card_card_expiration_year'] : '';
			$card_type				= isset( $_POST['cryptowallet_card_card_type'] ) ? $_POST['cryptowallet_card_card_type'] : '';
			$card_owner				= isset( $_POST['cryptowallet_card_card_owner'] ) ? $_POST['cryptowallet_card_card_owner'] : '';


			if (  strlen( $card_owner ) < 3 ) {
				wc_add_notice(__( 'Card Holder is required', 'cryptowallet' ) );
				return false;
			}

			// Determine if provided card security code contains numbers and is the proper length
			if ( ! ctype_digit( $card_csc ) ) {
				wc_add_notice(__( 'Card security code is invalid (only digits are allowed)', 'cryptowallet' ) );
				return false;
			}

			if (  strlen( $card_csc ) != 3 &&  strlen( $card_csc ) != 4  ) {
				wc_add_notice(__( 'Card security code is invalid (wrong length)', 'cryptowallet' ) );
				return false;
			}

			// Check card expiration date
			if ( ! ctype_digit( $card_exp_month ) ||
				! ctype_digit( $card_exp_year ) ||
				$card_exp_month > 12 ||
				$card_exp_month < 1 ||
				$card_exp_year < date('Y') ||
				$card_exp_year > date('Y') + 20
			) {
				wc_add_notice(__( 'Card expiration date is invalid', 'cryptowallet' ) );
				return false;
			}

			// Determine if a number was provided for the credit card number
			$card_number = str_replace( array( ' ', '-' ), '', $card_number );
			if( empty( $card_number ) || ! ctype_digit( $card_number ) ) {
				wc_add_notice(__( 'Card number is invalid', 'cryptowallet' ) );
				return false;
			}

			return true;
		}

		/**
		 * Validate plugin settings
		 */
		function validate_settings() {

			//Check for the CryptoWallet_Card Merchant merchant id, pin, and user id
			if ( ! $this->api_key || !$this->uid ) {
				return false;
			}

			return true;
		}
	}

	/**
	 * Add the CryptoWallet_Card Gateway to WooCommerce
	 */
	function add_cryptowallet_card_gateway( $methods ) {
		$methods[] = 'WC_Gateway_CryptoWallet_Card';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_cryptowallet_card_gateway' );
}
