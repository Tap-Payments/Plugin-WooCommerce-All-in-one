<?php

/*
Plugin Name: WooCommerce - Tap WebConnect
Description: Tap WebConnect is a plugin provided by Tap Payments that enables Tap for Woocommerce Version 2.0.0 or greater version.
Version: 2.0.1
Author: Waqas Zeeshan
*/

add_action('plugins_loaded', 'woocommerce_tap_init', 0); 
//define tap payment padge location
define('tap', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

//viewing the content of tap template
function woocommerce_tap_init(){
	if(!class_exists('WC_Payment_Gateway')) return;

    if( isset($_GET['msg']) && !empty($_GET['msg']) ){
        add_action('the_content', 'tap_showMessage');
    }
    function tap_showMessage($content){
            return '<div class="'.htmlentities(sanitize_text_field($_GET['type'])).'">'.htmlentities(urldecode(sanitize_text_field($_GET['msg']))).'</div>'.$content;
    }

    /**
     * Gateway class
     */
	class WC_tap extends WC_Payment_Gateway{
		public function __construct(){
			$this->id 					= 'tap';
			$this->method_title 		= 'tap';
			$this->has_fields 			= false; 
			$this->init_form_fields(); 
			$this->init_settings();
			$this->title 			= $this->settings['title'];
			$this->icon 			= tap.'logo.png';
			$this->redirect_page_id = $this->settings['redirect_page_id'];
			$this->test_public_key  = $this->settings['test_public_key'];
			$this->test_secret_key  = $this->settings['test_secret_key'];
			$this->live_public_key  = $this->settings['live_public_key'];
			$this->live_secret_key  = $this->settings['live_secret_key'];
			$this->post_url = $this->settings['post_url'];

			$this->msg['message'] 	= "";
			$this->msg['class'] 	= "";
			add_action('init', array(&$this, 'check_tap_response'));
			//update for woocommerce >2.0
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_tap_response' ) );
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				/* 2.0.0 */
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				/* 1.6.6 */
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			
			add_action('woocommerce_tapreceipt_tap', array(&$this, 'tapreceipt_page'));
			add_action( 'woocommerce_api_tap_webhook', array( $this, 'webhook' ) );
		}

		public function webhook($order_id) {
		         $data = json_decode(file_get_contents("php://input"), true);
		         $headers = apache_request_headers();
		         $header = getallheaders();
		         //var_dump($headers);exit;
		         $orderid = $data['reference']['order'];
		         $status = $data['status'];
		         $charge_id = $data['id'];
		         if ($status == 'CAPTURED') {
		            $order = wc_get_order($orderid);
			         $order->payment_complete();
			         $order->add_order_note(sanitize_text_field('Tap payment successful..').("<br>").('ID').(':'). ($charge_id.("<br>").('Payment Type :') . ($data['source']['payment_method']).("<br>").('Payment Ref:'). ($data['reference']['payment'])));
			         $order->reduce_order_stock();
			         update_option('webhook_debug', $_GET);
		         }
		         if ($status == 'DECLINED') {
		               $order = wc_get_order($orderid);
		               $order->update_status('pending');
			           $order->add_order_note(sanitize_text_field('Tap payment failed..').("<br>").('ID').(':'). ($charge_id.("<br>").('Payment Type :') . ($data['source']['payment_method']).("<br>").('Payment Ref:'). ($data['reference']['payment'])));
			           //update_option('webhook_debug', $_GET);
		         }
			}
    
		function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __('Enable/Disable', 'kdc'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable Tap Payment Module.', 'kdc'),
					'default' 		=> 'no',
					'description' 	=> 'Show in the Payment List as a payment option'
				),
				'title' => array(
					'title' 		=> __('Title:', 'kdc'),
					'type'			=> 'text',
					'default' 		=> __('Tap', 'kdc'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'kdc'),
					'desc_tip' 		=> true
				),
      			'test_public_key' => array(
                    'title'       => __('Test Public Key', 'kdc'),
                    'type'        => 'text',
					          'value'       => '',
					          'description' => __( 'Get your Test Public Key from Tap.','woocommerce' ),
					          'default'     => '',
					          'desc_tip'    =>true,
                    'required'    =>true),
				'test_secret_key' => array(
                    'title'       => __('Test Secret Key', 'kdc'),
                    'type'        => 'text',
					          'value'       => '',
					          'description' => __( 'Get your Test Secret Key from Tap.','woocommerce' ),
					          'default'     => '',
					          'desc_tip'    =>true,
                    'required'    =>true),
				'live_secret_key' => array(
                    'title'       => __('Live Secret Key', 'kdc'),
                    'type'        => 'text',
					          'value'       => '',
					          'description' => __( 'Get your Live Secret Key from Tap.','woocommerce' ),
					          'default'     => '',
					          'desc_tip'    =>true,
                    'required'    =>true),
                'live_public_key' => array(
                    'title'       => __('Live Public Key', 'kdc'),
                    'type'        => 'text',
					          'value'       => '',
                    'description' => __( 'Get your Live Public Key from Tap.', 'woocommerce' ),
          					'default'     => '',
          					'desc_tip'    => true,
                    'required'    => true
                   ),
      			'testmode' => array(
					'title' 		=> __('TEST Mode', 'kdc'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable Tap TEST Transactions.', 'kdc'),
					'default' 		=> 'no',
					'description' 	=> __('Tick to run TEST Transaction on the Tap platform'),
					'desc_tip' 		=> true
                ),
                'redirect_page_id' => array(
					'title' 		=> __('Return Page'),
					'type' 			=> 'select',
					'options' 		=> $this->tap_get_pages('Select Page'),
					'description' 	=> __('URL of success page', 'kdc'),
					'desc_tip' 		=> true
                 )
			);
		}


        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
		public function admin_options(){
			echo '<h3>'.__('Tap', 'kdc').'</h3>';
			echo '<p>'.__('Tap works by sending the user to Tap to enter their payment information.').'</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this ->generate_settings_html();
			echo '</table>';
		}
     		function tapreceipt_page($order){
			echo '<p>'.__('Thank you for your order, please click the button below to pay with Tap.', 'kdc').'</p>';
			echo $this->generate_tap_form($order);
		}
		
		/**
		* Process the payment and return the result
		**/
		function process_payment($order_id) {
			global $woocommerce;
			$order = wc_get_order($order_id);
            $currencyCode = $order->get_currency();
            $orderid = $order->get_id();
            $order_items = $order->get_items();
	 		$order_amount = $order->get_total();
	 		$table_prefix = $wpdb->prefix;
            if ($this->settings['testmode'] == "yes"){
	 			$active_sk = $this->test_secret_key;
	 			$active_pk = $this->test_public_key;
	 		}
	 		else {
	 			$active_sk = $this->live_secret_key;
	 			$active_pk = $this->live_public_key;
	 		}
            $charge_url = 'https://api.tap.company/v2/charges';
            $first_name = $_POST['billing_first_name'];
            $last_name = $_POST['billing_last_name'];
            $country = $_POST['billing_country'];
            $city = $_POST['billing_city'];
			$billing_address = $_POST['billing_address_1'];
            $url = $order->get_checkout_order_received_url();
			if ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) {
				$redirect_url = $order->get_checkout_order_received_url();
			} else {
				$redirect_url = get_permalink($this->redirect_page_id);
			}
			//For wooCoomerce 2.0
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			}

            $return_url = $redirect_url;
            $billing_email = $_POST['billing_email'];
            $biliing_fone = $_POST['billing_phone'];
            $avenue = $_POST['billing_address_2'];
            $order_amount = $order->get_total();
            $post_url = get_site_url()."/wc-api/tap_webhook";
            
            
            
	 		$source_id = 'src_all';
        	$trans_object["amount"]                   = $order_amount;
        	$trans_object["currency"]                 = $currencyCode;
        	$trans_object["threeDsecure"]             = true;
        	$trans_object["save_card"]                = false;
        	$trans_object["description"]              = $orderid;
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
        	$trans_object["customer"]["phone"]["country_code"]       = '971';
        	$trans_object["customer"]["phone"]["number"] = $biliing_fone;
        	$trans_object["source"]["id"] = $source_id;
        	$trans_object["post"]["url"] = $post_url;
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
					                "content-type: application/json"
					        ),
				));

			$response = curl_exec($curl);
			var_dump($response);exit;
			$err = curl_error($curl);
			$obj = json_decode($response);
			$charge_id = $obj->id;
			$redirct_Url = $obj->transaction->url;
	            return array(
					'result' => 'success',
					'redirect' => $redirct_Url
				);
		}
		/**
		* Check for valid Tap server callback
		**/
		function check_tap_response() {
			global $woocommerce;
			$charge_url = 'https://api.tap.company/v2/charges';
			if ( $this->settings['testmode'] == "yes" ) {
	 			$active_sk = $this->test_secret_key;
	 		}
	 		else {
	 			$active_sk = $this->live_secret_key;
	 		}
			
			$curl = curl_init();

			curl_setopt_array($curl, array(
			  		CURLOPT_URL => "https://api.tap.company/v2/charges/".$_GET['tap_id'],
			  		CURLOPT_RETURNTRANSFER => true,
			  		CURLOPT_ENCODING => "",
			  		CURLOPT_MAXREDIRS => 10,
			  		CURLOPT_TIMEOUT => 30,
			  		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  		CURLOPT_CUSTOMREQUEST => "GET",
			  		CURLOPT_POSTFIELDS => "{}",
			  		CURLOPT_HTTPHEADER => array(
			    		"authorization: Bearer ".$active_sk,
			  		),
				)
			);

			$response = curl_exec($curl);
			$obj = json_decode($response);
			$exists = get_user_meta(get_current_user_id(), '_tap_customer_id_');
			$order_id = $obj->reference->order;
			$order = wc_get_order($order_id);
			$response = json_decode($response);
			if ($response->status == 'CAPTURED') {
				$order->update_status('processing');
				$order->payment_complete($_GET['tap_id']);
				update_post_meta( $order->id, '_transaction_id', $_GET['tap_id']);
				$order->set_transaction_id( $_GET['tap_id'] );
				$order->add_order_note(sanitize_text_field('Tap payment successful').("<br>").('ID').(':'). ($_GET['tap_id'].("<br>").('Payment Type :') . ($response->source->payment_method).("<br>").('Payment Ref:'). ($response->reference->payment)));
				$order->payment_complete($_GET['tap_id']);
				$woocommerce->cart->empty_cart(); 
				$url = $order->get_checkout_order_received_url();
				wp_redirect( esc_url_raw( add_query_arg('ref', $ref , $url)));
                exit;
				}
				if ($response->status !== 'CAPTURED') {
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
    					//$woocommerce->cart->add_to_cart( $product_id, $product_quantity, $product_variation_id , $variationName);
					}
 					$cart_url = $woocommerce->cart->get_cart_url();
 						wp_redirect($cart_url);
 						wc_add_notice( 'Payment Failed!', 'error' );
				}
				$err = curl_error($curl);
				curl_close($curl);
				if ($err) {
					echo "cURL Error #:" . $err;
				} else {
						echo $response->code;
				}
 		}
	
		// get all pages
		function tap_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
    		}
    function process_refund($order_id, $amount = null, $reason = '')
		{
			global $post, $woocommerce;
			$order = new WC_Order($order_id);
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
//print_r($json_request);exit;

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
				    		"authorization: Bearer ".$this->live_secret_key,
				    		"content-type: application/json"
			  		),
));

$response = curl_exec($curl);;
//print_r($response);exit;
	 		$response = json_decode($response);
	 		if ($response->id) {
	 			if ( $response->status == 'PENDING') {
	 				$order->add_order_note(sanitize_text_field('Tap Refund successful').("<br>").'Refund ID'.("<br>"). $response->id);
	 						return true;
	 			}
	 		}
	 		else { 
	 			return false;
	 		}
		}
		}
		/**
		* Add the Gateway to WooCommerce
		**/
		function woocommerce_add_tap_gateway($methods) {
			$methods[] = 'WC_tap';
			return $methods;
		}

		add_filter('woocommerce_payment_gateways', 'woocommerce_add_tap_gateway' );
	}
