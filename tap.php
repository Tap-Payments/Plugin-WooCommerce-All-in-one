<?php

/*
 * Plugin Name: WooCommerce Tap Payment Gateway
 * Plugin URI: 
 * Description: Take credit card payments on your store. (Features : All In One - Popup, Redirect, Tokenization)
 * Author: Waqas Zeeshan
 * Author URI: https://tap.company/
 * Version: 2.1
 */
 
 /* This action hook registers our PHP class as a WooCommerce payment gateway */

add_filter( 'woocommerce_payment_gateways', 'tap_add_gateway_class' );

function tap_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Tap_Gateway';
	return $gateways;
}

add_action( 'plugins_loaded', 'tap_init_gateway_class' );
define('tap_imgdir', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function tap_init_gateway_class() {

	class WC_Tap_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {

 			global $woocommerce;

			$this->id = 'tap'; // payment gateway plugin ID
			$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'Tap Gateway';
			$this->method_description = 'Get Paid via Tap Gateway'; // will be displayed on  the options page

			$this->supports = array(
				'products',
				'refunds',
				'subscriptions',
				'pre-orders',
				'tokenization'
			);
			// Method with all the options fields
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
			
         $this->icon = tap_imgdir . 'logo.png';
			$this->title = $this->get_option('title');
		   $this->failer_page_id = $this->settings['failer_page_id'];
			$this->success_page_id = $this->settings['success_page_id'];
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option('enabled');
			$this->testmode = 'yes' === $this->get_option('testmode');
			$this->api_key = $this->get_option('api_key');
         $this->test_secret_key = $this->get_option('test_secret_key');
         $this->test_public_key = $this->get_option('test_public_key');
         $this->live_secret_key = $this->get_option('live_secret_key');
         $this->live_public_key = $this->get_option('live_public_key');
			$this->payment_mode = $this->get_option('payment_mode');
			$this->type = 'type';
			$this->ui_mode = $this->get_option('ui_mode');
			$this->ui_language = $this->get_option('ui_language');
         $this->post_url = $this->get_option('post_url');
			$this->save_card = $this->get_option('save_card');
			
			if($this->ui_mode == 'tokenization'){
				
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'woocommerce_order_status_completed', array($this, 'update_order_status'), 10, 1);

				// We need custom JavaScript to obtain a token
				add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ), 11);
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ), 11);
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			
			add_action( 'woocommerce_receipt_tap', array( $this, 'tap_checkout_receipt_page' ) );
			add_action( 'woocommerce_thankyou_tap', array( $this, 'tap_thank_you_page' ) );
			add_action( 'woocommerce_api_tap_webhook', array( $this, 'webhook' ) );
		}

		public function webhook($order_id) {
         $data = json_decode(file_get_contents("php://input"), true);
         $headers = apache_request_headers();
         $header = getallheaders();
         
         $orderid = $data['reference']['order'];
         $status = $data['status'];
         $charge_id = $data['id'];
        	$order = wc_get_order($orderid);

         if ($status == 'CAPTURED'){
         	//$order = wc_get_order($orderid);
         	$order->payment_complete();
         	$order->add_order_note(sanitize_text_field('Tap payment successful..').("<br>").('ID').(':'). ($charge_id.("<br>").('Payment Type :') . ($data['source']['payment_method']).("<br>").('Payment Ref:'). ($data['reference']['payment'])));
         	$order->reduce_order_stock();
         	update_option('webhook_debug', $_GET);
         }
         if($status == 'DECLINED'){
            //$order = wc_get_order($orderid);
            $order->update_status('pending');
           	$order->add_order_note(sanitize_text_field('Tap payment failed..').("<br>").('ID').(':'). ($charge_id.("<br>").('Payment Type :') . ($data['source']['payment_method']).("<br>").('Payment Ref:'). ($data['reference']['payment'])));
           update_option('webhook_debug', $_GET);
         }
		}


		public function tap_thank_you_page($order_id){
			$active_sk = '';
			if($this->testmode == '1') {
	 			$active_sk = $this->test_secret_key;
			}else {
	 			$active_sk = $this->live_secret_key;
			}

			if($this->ui_mode == 'popup' || $this->ui_mode == 'redirect' || $this->ui_mode == 'tokenization' ){
	    
 				global $woocommerce;
 				$order = wc_get_order( $order_id );
 				//Custore cancel the payment or back button
	 			if (empty($_GET['tap_id'])){
	 				$items = $order->get_items();
	 					foreach ( $items as $item ) {
	    					$product_name = $item->get_name();
	    					$product_quantity = $item->get_quantity();
	   		 				$product_id = $item->get_product_id();
	    					$product_variation_id = $item->get_variation_id();
	    					$variation = new WC_Product_Variation($product_variation_id);
	    					$variationName = implode(" / ", $variation->get_variation_attributes());
	    					$woocommerce->cart->add_to_cart( $product_id, $product_quantity, $product_variation_id , $variationName);
						}
	 				$cart_url = $woocommerce->cart->get_cart_url();
	 				wp_redirect($cart_url);	
	 			}
 	
 				if (!empty($_GET['tap_id']) && $this->payment_mode == 'charge') {
 				
	 				$curl = curl_init();
			 		curl_setopt_array($curl, array(
		  			CURLOPT_URL => "https://api.tap.company/v2/charges/".$_GET['tap_id'],
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => "",
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => 30,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => "GET",
						CURLOPT_HTTPHEADER => array(
						    "authorization: Bearer ".$active_sk,
						    "content-type: application/json"
				  		),
					));
					$response = curl_exec($curl);
					$response = json_decode($response);
					//Put Back && $response->post->status == 'ERROR'
					if ($response->status == 'CAPTURED' && $response->post->status == 'ERROR') {
						$order->update_status('processing');
						$order->payment_complete($_GET['tap_id']);
						update_post_meta( $order->id, '_transaction_id', $_GET['tap_id']);
						$order->set_transaction_id( $_GET['tap_id'] );
						$order->add_order_note(sanitize_text_field('Tap payment successful').("<br>").('ID').(':'). ($_GET['tap_id'].("<br>").('Payment Type :') . ($response->source->payment_method).("<br>").('Payment Ref:'). ($response->reference->payment)));
						$order->payment_complete($_GET['tap_id']);
						$woocommerce->cart->empty_cart();
						if ( $this->success_page_id == "" || $this->success_page_id == 0 ) {
							$redirect_url = $order->get_checkout_order_received_url();
						} else {
							$redirect_url = get_permalink($this->success_page_id);
							wp_redirect($redirect_url);exit;
						}
					}
					//Put back && $response->post->status == 'ERROR'
					if ($response->status !== 'CAPTURED' && $response->post->status == 'ERROR'){
				    
						$order->update_status('pending');
						$order->add_order_note(sanitize_text_field('Tap payment failed').("<br>").('ID').(':'). ($_GET['tap_id'].("<br>").('Payment Type :') . ($response->source->payment_method).("<br>").('Payment Ref:'). ($response->reference->payment)));
						$items = $order->get_items();
	 					foreach ( $items as $item ) {
	    					$product_name = $item->get_name();
	    					$product_quantity = $item->get_quantity();
	   		 				$product_id = $item->get_product_id();
	    					$product_variation_id = $item->get_variation_id();
	    					$variation = new WC_Product_Variation($product_variation_id);
	    					$variationName = implode(" / ", $variation->get_variation_attributes());
	    					$woocommerce->cart->add_to_cart( $product_id, $product_quantity, $product_variation_id , $variationName);
						}
						$failure_url = get_permalink($this->failer_page_id);
						wp_redirect($failure_url);
						wc_add_notice( __('Transaction Failed ', 'woothemes') . $error_message, 'error' );
		            return;
						exit;
					}
					
					$err = curl_error($curl);
					curl_close($curl);

					if ($response->status !== 'CAPTURED' && $response->post->status == 'SUCCESS'){
				    
 				    	$items = $order->get_items();
	 					foreach ( $items as $item ) {
	    					$product_name = $item->get_name();
	    					$product_quantity = $item->get_quantity();
	   		 				$product_id = $item->get_product_id();
	    					$product_variation_id = $item->get_variation_id();
	    					$variation = new WC_Product_Variation($product_variation_id);
	    					$variationName = implode(" / ", $variation->get_variation_attributes());
	    					$woocommerce->cart->add_to_cart( $product_id, $product_quantity, $product_variation_id , $variationName);
						}
		 				$failure_url = get_permalink($this->failer_page_id);
						wp_redirect($failure_url);
						wc_add_notice( __('Transaction Failed ', 'woothemes') . $error_message, 'error' );
		            return;
						exit;
 					}
 				}
 			
 				if (!empty($_GET['tap_id']) && $this->payment_mode == 'authorize') {
 					
 					$curl = curl_init();
			 		curl_setopt_array($curl, array(
		  			CURLOPT_URL => "https://api.tap.company/v2/authorize/".$_GET['tap_id'],
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => "",
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => 30,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => "GET",
						CURLOPT_HTTPHEADER => array(
						    "authorization: Bearer ".$active_sk,
						    "content-type: application/json"
				  		),
					));
					$response = curl_exec($curl);
					$response = json_decode($response);
					
					if ($response->status == 'AUTHORIZED') {
						$order->update_status('pending');
						$order->add_order_note(sanitize_text_field('Tap payment successful').("<br>").('ID').(':'). ($_GET['tap_id'].("<br>").('Payment Type :') . ($response->source->payment_method).("<br>").('Payment Ref:'). ($response->reference->payment)));
						$woocommerce->cart->empty_cart();
						if ( $this->success_page_id == "" || $this->success_page_id == 0 ) {
							$redirect_url = $order->get_checkout_order_received_url();
						} else {
							$redirect_url = get_permalink($this->success_page_id);
							wp_redirect($redirect_url);exit;
						} 
					}else {
						$order->update_status('pending');
					 	if ( $this->failer_page_id == "" || $this->failer_page_id == 0 ) {
							$failure_url =  $this->get_return_url($order);
						} else {
							$failure_url = get_permalink($this->failer_page_id);
							wp_redirect($failure_url);
							wc_add_notice( __('Transaction Failed ', 'woothemes') . $error_message, 'error' );
                   	return;
							exit;
						}
					}
					$err = curl_error($curl);
					curl_close($curl);

					if ($err) {
						echo "cURL Error #:" . $err;
					} else {
						echo $response->code;
					}
 				}
 			}
 		}


 		public function tap_checkout_receipt_page($order_id) {
 			global $woocommerce;
 			
 			$items = WC()->cart->get_cart();
 			$items = array_values(($items));
 			$order = wc_get_order( $order_id );
 			if($this->testmode == "testmode"){
            $active_pk = $this->test_public_key;
            $active_sk = $this->test_secret_key;
 			}else{
 				$active_pk = $this->live_public_key;
 				$active_sk = $this->live_secret_key;
 			}
 			$ref = '';
 			if($order->currency=="KWD"){
				$Total_price = number_format((float)$order->total, 3, '.', '');
			}else{
				$Total_price = number_format((float)$order->total, 2, '.', '');
			}
         $Hash = 'x_publickey'. $active_pk.'x_amount'.$Total_price.'x_currency'.$order->currency.'x_transaction'.$ref.'x_post'.get_site_url()."/wc-api/tap_webhook";
         $hashstring = hash_hmac('sha256', $Hash, $active_sk);
 		    
 			echo '<div id="tap_root"></div>';
 			echo '<input type="hidden" id="publishable_key" value="' . $this->live_public_key . '" />';
         echo '<input type="hidden" id="test_public_key" value="' . $this->test_public_key . '" />';
         echo '<input type="hidden" id="testmode" value="' . $this->testmode . '" />';
         echo '<input type="hidden" id="post_url" value="' . get_site_url()."/wc-api/tap_webhook" . '" />';
 			echo '<input type="hidden" id="tap_end_url" value="' . $this->get_return_url($order) . '" />';
 			echo '<input type="hidden" id="order_id" value="' . $order->id . '" />';
 			echo '<input type="hidden" id="hashstring" value="' . $hashstring . '" />';
 			echo '<input type="hidden" id="ui_language" value="' . $this->ui_language . '" />';
 			
 			echo '<input type="hidden" id="chg" value="' . $this->payment_mode . '" />';
 			echo '<input type="hidden" id="save_card" value="' . $this->save_card . '" />';
 			echo '<input type="hidden" id="payment_mode" value="' . $this->payment_mode . '" />';
 			echo '<input type="hidden" id="amount" value="' . $order->total . '" />';
 			echo '<input type="hidden" id="currency" value="' . $order->currency . '" />';
 			echo '<input type="hidden" id="billing_first_name" value="' . $order->billing_first_name . '" />';
 			echo '<input type="hidden" id="billing_last_name" value="' . $order->billing_last_name . '" />';
 			echo '<input type="hidden" id="billing_email" value="' . $order->billing_email . '" />';
 			echo '<input type="hidden" id="billing_phone" value="' . $order->billing_phone . '" />';
 			echo '<input type="hidden" id="customer_user_id" value="' . get_current_user_id() . '" />';
 			echo '<input type="hidden" id="example" value="example"/>';
 			
 			foreach($items as $key=>$item) {
 				$price = $item['data']->price;
 				if($item['data']->sale_price){
 					$price = $item['data']->sale_price;
 				}
 				echo '<input type="hidden" name="items_bulk[]" data-name="'.$item['data']->name.'" data-quantity="'.$item['quantity'].'" data-sale-price="'.$price.'" data-item-product-id="'.$item['product_id'].'" data-product-total-amount="'.$item['quantity']*$price.'" class="items_bulk">';
 			}
 			if ( $this->ui_mode == 'popup') {
 				?>	
             <script type="text/javascript">
             jQuery(function(){
					
					jQuery("#submit_tap_payment_form").click();});
             </script>
            <?php
 				echo '<input type="button" value="Place order by Tap" id="submit_tap_payment_form" onclick="goSell.openLightBox()" />';
 			}	
 		}


		public function init_form_fields(){

			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Tap Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Credit Card',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via Tap payment gateway. On clicking Place order payment will be processed.',
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
       		'test_secret_key' => array(
             	'title' => 'Test Secret Key',
             	'type'  => 'text'
            ),
       	  'test_public_key' => array(
             'title' => 'Test Public Key',
             'type'  => 'text'
            ),
       	   'live_public_key' => array(
             	'title' => 'Live Public Key',
             	'type'  => 'text'
            ),
       		'live_secret_key' => array(
               'title' => 'Live Secret Key',
               'type'  => 'text'
            ),
				'payment_mode' => array(
               'title'       => 'Payment Mode',
               'type'        => 'select',
               'class'       => 'wc-enhanced-select',
               'default'     => '',
               'desc_tip'    => true,
               'options'     => array(
						'charge'    => 'Charge',
						'authorize' => 'Authorize',
						)
		 		),
		 		'ui_mode' => array(
               'title'       => 'Ui Mode',
               'type'        => 'select',
               'class'       => 'wc-enhanced-select',
               'default'     => '',
               'desc_tip'    => true,
               'options'     => array(
						'redirect' => 'redirect',
						'popup'    => 'popup',
						'tokenization' => 'Tokanization',
						)
		 		),
		 		'ui_language' => array(
             	'title'       => 'Ui Language',
             	'type'        => 'select',
             	'class'       => 'wc-enhanced-select',
             	'default'     => '',
             	'desc_tip'    => true,
             	'options'     => array(
						'english' => 'English',
						'arabic'  => 'Arabic',	
 					)
		 		),
		 		'failer_page_id' => array(
					'title' 	=> __('Return to failure Page'),
					'type' 	=> 'select',
					'options' => $this->tap_get_pages('Select Page'),
					'description' => __('URL of failure page', 'kdc'),
					'desc_tip' => true
            ),
		 		'success_page_id' => array(
					'title' 	=> __('Return to success Page'),
					'type' 	=> 'select',
					'options' 	=> $this->tap_get_pages('Select Page'),
					'description' => __('URL of success page', 'kdc'),
					'desc_tip' 	=> true
            ),	
		 		'post_url' => array(
					'title' => 'Post URL',
					'type'  => 'text'
				),
		 		'save_card' => array(
					'title'  => 'Save Cards',
					'label'		=> 'Check if you want to save card data',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				)
			);
	 	}

		public function admin_scripts(){
 			 wp_enqueue_script('tap2', plugin_dir_url(__FILE__) . '/tap2.js');
 		}

      public function payment_scripts() {
			if ($this->ui_mode == 'tokenization'){
 				echo '<script type="text/javascript">var WEB_URL = "' . home_url('/') . '";</script>';
            wp_register_style( 'tap_payment',  plugins_url('tap-payment.css', __FILE__));
			   wp_enqueue_script( 'tap_js', 'https://cdnjs.cloudflare.com/ajax/libs/bluebird/3.3.4/bluebird.min.js' );
			   wp_enqueue_script( 'tap_js2', 'https://secure.gosell.io/js/sdk/tapjsli.js' );
				wp_register_script( 'woocommerce_tap', plugins_url( 'tap.js', __FILE__ ), array( 'jquery', 'tap_js' ) );
				wp_localize_script( 'woocommerce_tap', 'tap_params', array(
					'publishableKey' => $this->live_public_key
				));
				wp_enqueue_style( 'tap-payment', plugins_url( 'tap-payment.css', __FILE__ ) );
				wp_enqueue_script( 'woocommerce_tap' );
			
			}

			if ($this->ui_mode == 'popup' || $this->ui_mode == 'redirect' ){
			   wp_register_style( 'tap_payment',  plugins_url('tap-payment.css', __FILE__));
		 		wp_enqueue_style('tap_payment');
		 		wp_register_style( 'tap_style', '//goSellJSLib.b-cdn.net/v1.6.1/css/gosell.css' );
		 		wp_register_style( 'tap_icon', '//goSellJSLib.b-cdn.net/v1.6.1/imgs/tap-favicon.ico' );
				wp_enqueue_style('tap_style');
				wp_enqueue_style('tap_icon');
				wp_enqueue_script( 'tap_js', '//goSellJSLib.b-cdn.net/v1.6.1/js/gosell.js', array('jquery') );
				wp_register_script( 'woocommerce_tap', plugins_url( 'taap.js', __FILE__ ), 'gosell');
				wp_enqueue_style( 'tap-payment', plugins_url( 'tap-payment.css', __FILE__ ) );
				wp_enqueue_script( 'woocommerce_tap' );
		   }
	 	}

		public function payment_fields() {
			
			if ($this->ui_mode == 'tokenization'){

				echo '<form id="form-container" method="post" action="">
          			<!-- Tap element will be here -->
          			<div id="element-container"></div>  
          			<div id="error-handler" role="alert"></div>
          			<div id="success" style="color:transparent;">
                    <span id="token"></span>
                       </div>
          			<!-- Tap pay button -->
          			<button id="tap-btn" style="display: none;
                   visibility: hidden;">Submit</button>
      			'.'
          			 <input type="hidden" id="publishable_key" value="' . $this->live_public_key . '" />
          			 <input type="hidden" id="test_public_key" value="' . $this->test_public_key . '" />
          			 <input type="hidden" id="testmode" value="' . $this->testmode . '" />
          			 <input type="hidden" id="ui_mode" value="' . $this->ui_mode . '" />
      			</form>';
			}

			if ($this->ui_mode == 'popup' || $this->ui_mode == 'redirect' ){

           	global $woocommerce;
				$customer_user_id = get_current_user_id();
				$currency = get_woocommerce_currency();
				$amount = $woocommerce->cart->total;
				$mode = $this->payment_mode;
	 			if ( $this->description ) {
					if ( $this->testmode ) {
						$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers mentioned in documentation';
						$this->description  = trim( $this->description );
					}
					echo wpautop( wp_kses_post( $this->description ) );
				}
				echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
			
				do_action( 'woocommerce_credit_card_form_start', $this->id );
			?>
	 			<div id="tap_root"></div>
	 			<?php
				do_action( 'woocommerce_credit_card_form_end', $this->id );
				echo '<div class="clear"></div></fieldset>';
 			}	

		}

	 	public function process_payment($order_id) {

	 		$order 	= new WC_Order( $order_id );

	 		if ($this->ui_mode == 'popup'){
	 			global $woocommerce;
				
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				);
			}

			if ($this->ui_mode == 'redirect') {
				$currencyCode = $order->get_currency();
			   $orderid = $order->get_id();
			   $order_items = $order->get_items();
				$order_amount = $order->get_total();
				$table_prefix = $wpdb->prefix;

			   $charge_url = 'https://api.tap.company/v2/charges';
			   $first_name = $_POST['billing_first_name'];
			   $last_name  = $_POST['billing_last_name'];
			   $country    = $_POST['billing_country'];
			   $city       = $_POST['billing_city'];
				$billing_address = $_POST['billing_address_1'];

			   $return_url    = $order->get_checkout_order_received_url();
			   $billing_email = $_POST['billing_email'];
			   $biliing_fone  = $_POST['billing_phone'];
			   $avenue = $_POST['billing_address_2'];
			   $order_amount  = $order->get_total();
			   $post_url      = get_site_url()."/wc-api/tap_webhook";
			    
				$source_id = 'src_all';
				$trans_object["amount"]                   = $order_amount;
				$trans_object["currency"]                 = $currencyCode;
				$trans_object["threeDsecure"]             = true;
				$trans_object["save_card"]                = false;
				$trans_object["description"]              = $orderid;
				$trans_object["statement_descriptor"]     = 'Sample';
				$trans_object["metadata"]["udf1"]         = 'test';
				$trans_object["metadata"]["udf2"]         = 'test';
				$trans_object["reference"]["transaction"] = 'txn_0001';
				$trans_object["reference"]["order"]       = $orderid;
				$trans_object["receipt"]["email"]         = false;
				$trans_object["receipt"]["sms"]           = true;
				$trans_object["customer"]["first_name"]   = $first_name;
				$trans_object["customer"]["last_name"]    = $last_name;
				$trans_object["customer"]["email"]        = $billing_email;
				$trans_object["customer"]["phone"]["country_code"]  = '971';
				$trans_object["customer"]["phone"]["number"] = $biliing_fone;
				$trans_object["source"]["id"] = $source_id;
				$trans_object["post"]["url"]  = $post_url;
				$trans_object["redirect"]["url"] = $return_url;
				$frequest = json_encode($trans_object);
				$frequest = stripslashes($frequest);
				
				if($this->testmode == "testmode"){
                $active_pk = $this->test_public_key;
                $active_sk = $this->test_secret_key;
	 			}else{
	 				$active_pk = $this->live_public_key;
	 				$active_sk = $this->live_secret_key;
	 			}

				$curl = curl_init();
				curl_setopt_array($curl, array(
				  	CURLOPT_URL => $charge_url,
			  		CURLOPT_RETURNTRANSFER => true,
			  		CURLOPT_ENCODING => "",
			  		CURLOPT_MAXREDIRS => 10,
			  		CURLOPT_TIMEOUT => 30,
			  		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  		CURLOPT_CUSTOMREQUEST => "POST",
			  		CURLOPT_POSTFIELDS => $frequest,
			  		CURLOPT_HTTPHEADER => array(
			            "authorization: Bearer ".$active_sk,
			            "content-type: application/json"
			        ),
				));

				$response = curl_exec($curl);
				$err = curl_error($curl);
				$obj = json_decode($response);
				$charge_id   = $obj->id;
				$redirct_Url = $obj->transaction->url;
			    
			    return array(
					'result'   => 'success',
					'redirect' => $redirct_Url
				);
			}

			if ($this->ui_mode == 'tokenization'){
	 			
	 			global $woocommerce,$wpdb;
	 			
	 			$token = new WC_Payment_Token_CC();
	 			$wooToken = $_POST['tap-woo-token'];
	 			$order = wc_get_order($order_id);
            $currencyCode = $order->get_currency();
            $orderid = $order->get_id();
	 			$order_amount = $order->get_total();
	 			$table_prefix = $wpdb->prefix;
	 			
            if($this->testmode == "testmode"){
                $active_pk = $this->test_public_key;
                $active_sk = $this->test_secret_key;
	 			}else{
	 				$active_pk = $this->live_public_key;
	 				$active_sk = $this->live_secret_key;
	 			}
 			
	 			$ref = '';
	 			if($currencyCode=="KWD"){
					$order_amount = number_format((float)$order->get_total(), 3, '.', '');
			   }else{
					$order_amount = number_format((float)$order->get_total(), 2, '.', '');
		   	}

            $Hash = 'x_publickey'. $active_pk.'x_amount'.$order_amount.'x_currency'.$currencyCode.'x_transaction'.$ref.'x_post'.get_site_url()."/wc-api/tap_webhook";
            $hashstring = hash_hmac('sha256', $Hash, $active_sk);
               
            $charge_url = 'https://api.tap.company/v2/charges';
            $first_name = $_POST['billing_first_name'];
            $last_name = $_POST['billing_last_name'];
            $country = $_POST['billing_country'];
            $city = $_POST['billing_city'];
			   $billing_address = $_POST['billing_address_1'];
            $return_url = $order->get_checkout_order_received_url(); 
            $billing_email = $_POST['billing_email'];
            $biliing_fone = $_POST['billing_phone'];
            $avenue = $_POST['billing_address_2'];
            $order_amount = $order->get_total();

            if( $this->save_card == 'no') {
              $save_card_val = false;
            }else {
              $save_card_val = true;
            }
          	
     			$trans_object["amount"]                   = $order_amount;
     			$trans_object["currency"]                 = $currencyCode;
     			$trans_object["threeDsecure"]             = true;
     			$trans_object["save_card"]                = $save_card_val;
     			$trans_object["description"]              = 'Test Description';
     			$trans_object["statement_descriptor"]     = 'Sample';
     			$trans_object["metadata"]["udf1"]          = 'test';
     			$trans_object["metadata"]["udf2"]          = 'test';
     			$trans_object["reference"]["transaction"]  = 'txn_0001';
     			$trans_object["reference"]["order"]        = $orderid;
     			
     			$trans_object["receipt"]["email"]          = false;
     			$trans_object["receipt"]["sms"]            = true;
     			$trans_object["customer"]["first_name"]    = $first_name;
     			$trans_object["customer"]["last_name"]    = $last_name;
     			$trans_object["customer"]["email"]        = $billing_email;
     			$trans_object["customer"]["phone"]["country_code"]       = '';
     			$trans_object["customer"]["phone"]["number"] = $biliing_fone;
     			$trans_object["source"]["id"] = $wooToken;
     			$trans_object["post"]["url"] = get_site_url()."/wc-api/tap_webhook";
     			$trans_object["redirect"]["url"] = $return_url;
     			$frequest = json_encode($trans_object);
     			$frequest = stripslashes($frequest);

				$curl = curl_init();

				curl_setopt_array($curl, array(
				  CURLOPT_URL => $charge_url,
				  CURLOPT_RETURNTRANSFER => true,
				  CURLOPT_ENCODING => "",
				  CURLOPT_MAXREDIRS => 10,
				  CURLOPT_TIMEOUT => 30,
				  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				  CURLOPT_CUSTOMREQUEST => "POST",
				  CURLOPT_POSTFIELDS => $frequest,
				  CURLOPT_HTTPHEADER => array(
                     "authorization: Bearer ".$active_sk,
                     "hashstring:".$hashstring,
                     "content-type: application/json"
	               ),
				));

				$response = curl_exec($curl);
				$err = curl_error($curl);
				$obj = json_decode($response);
        		
        		if ($obj->transaction->url == '') {
		        	if ( $this->failer_page_id == "" || $this->failer_page_id == 0 ) {
						$failure_url =  $this->get_return_url($order);
					} else {
						$failure_url = get_permalink($this->failer_page_id);
					}
		         return array(
		            'result' => 'failure',
		            'redirect' => $failure_url
		         );
        		}
				
				return array(
					'result' => 'success',
					'redirect' => $obj->transaction->url
				);
			}
		}

		public function tap_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				//show indented child pages?
				if ($indent) {
             	$has_parent = $page->post_parent;
             	while($has_parent) {
                 	$prefix .=  ' - ';
                 	$next_page = get_post($has_parent);
                 	$has_parent = $next_page->post_parent;
             	}
         	}
            //add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
    	}

    	public function process_refund($order_id, $amount = null, $reason = ''){
			global $post, $woocommerce;
			
			$order   = new WC_Order($order_id);
			$transID = get_post_meta( $order->id, '_transaction_id');
			$currency = $order->currency;
	 		$refund_url = 'https://api.tap.company/v2/refunds';
	 		$refund_request['charge_id'] = $transID;
	 		$refund_request['amount'] = $amount;
	 		$refund_request['currency'] = $currency;
	 		$refund_request['description'] = "Description";
	 		$refund_request['reason'] = $reason;
         $refund_request['reference']['merchant']  = "txn_0001";	 
	      $refund_request['metadata']['udf1']= "test1";
	      $refund_request['metadata']['udf2']= "test2";
	      $refund_request['post']['url']  = "http://your_url.com/post";
	 		$json_request = json_encode($refund_request);
	 		$json_request = str_replace( '\/', '/', $json_request );
	 		$json_request = str_replace(array('[',']'),'',$json_request);

	 		$active_sk = '';
			if($this->testmode == '1') {
	 			$active_sk = $this->test_secret_key;
			}else {
	 			$active_sk = $this->live_secret_key;
			}

			$curl = curl_init();

			curl_setopt_array($curl, array(
			  CURLOPT_URL => "https://api.tap.company/v2/refunds",
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => "",
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 30,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => "POST",
			  CURLOPT_POSTFIELDS =>$json_request,
			  CURLOPT_HTTPHEADER => array(
		    		"authorization: Bearer ".$active_sk,
		    		"content-type: application/json"
			  	),
			));

			$response = curl_exec($curl);;
	 		$response = json_decode($response);
	 		if ($response->id) {
	 			if ( $response->status == 'PENDING') {
	 				$order->add_order_note(sanitize_text_field('Tap Refund successful').("<br>").'Refund ID'.("<br>"). $response->id);
	 						return true;
	 			}
	 		}else { 
	 			return false;
	 		}
		}
   }
}