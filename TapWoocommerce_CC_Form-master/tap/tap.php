<?php



/*



 * Plugin Name: WooCommerce Tap Payment Gateway



 * Plugin URI: 



 * Description: Take credit card payments on your store.



 * Author: Waqas Zeeshan



 * Author URI: https://tap.company/



 * Version: 2.1











 /*



 * This action hook registers our PHP class as a WooCommerce payment gateway



 */



add_filter( 'woocommerce_payment_gateways', 'tap_add_gateway_class' );
add_filter('woocommerce_checkout_fields', 'apsaviation_add_class_to_wc_checkout_fields');

function apsaviation_add_class_to_wc_checkout_fields($fields) {
	$fields['billing']['billing_first_name']['class'] = array('text--no-margins row__col-6');
	$fields['billing']['billing_last_name']['class'] = array('text--no-margins row__col-6');
	$fields['billing']['billing_company']['class'] = array('text--no-margins row__col-12');
	$fields['billing']['billing_country']['class'] = array('text--no-margins row__col-12');
	$fields['billing']['billing_address_1']['class'] = array('text--no-margins row__col-12');
	$fields['billing']['billing_address_2']['class'] = array('text--no-margins row__col-12');
	$fields['billing']['billing_city']['class'] = array('text--no-margins row__col-4');
	$fields['billing']['billing_state']['class'] = array('text--no-margins row__col-4');
	$fields['billing']['billing_postcode']['class'] = array('text--no-margins row__col-4');
	$fields['billing']['billing_phone']['class'] = array('text--no-margins row__col-6');
	$fields['billing']['billing_email']['class'] = array('text--no-margins row__col-6');

	$fields['shipping']['shipping_first_name']['class'] = array('text--no-margins row__col-6');
	$fields['shipping']['shipping_last_name']['class'] = array('text--no-margins row__col-6');
	$fields['shipping']['shipping_company']['class'] = array('text--no-margins row__col-12');
	$fields['shipping']['shipping_country']['class'] = array('text--no-margins row__col-12');
	$fields['shipping']['shipping_address_1']['class'] = array('text--no-margins row__col-12');
	$fields['shipping']['shipping_address_2']['class'] = array('text--no-margins row__col-12');
	$fields['shipping']['shipping_city']['class'] = array('text--no-margins row__col-4');
	$fields['shipping']['shipping_state']['class'] = array('text--no-margins row__col-4');
	$fields['shipping']['shipping_postcode']['class'] = array('text--no-margins row__col-4');

	$fields['order']['order_comments']['class'] = array('text--no-margins row__col-12');

	return $fields;
}


function tap_add_gateway_class( $gateways ) {



	$gateways[] = 'WC_Tap_Gateway'; // your class name is here



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

			


			$this->title = $this->get_option( 'title' );

			$this->description = $this->get_option( 'description' );

			$this->enabled = $this->get_option( 'enabled' );

			$this->testmode = 'yes' === $this->get_option( 'testmode' );

			$this->api_key = $this->get_option('api_key');

            $this->test_secret_key = $this->get_option('test_secret_key');

            $this->test_public_key = $this->get_option('test_public_key');

            $this->live_secret_key = $this->get_option('live_secret_key');

            $this->live_public_key = $this->get_option('live_public_key');

			$this->payment_mode = $this->get_option('payment_mode');

			$this->type = 'type';

			//$this->save_card = $this->get_option('save_card');

	        //add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ) );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_filter( 'woocommerce_admin_order_actions', 'add_custom_order_status_actions_button', 100, 2 );

			//add_action('transition_post_status', array($this, 'pending_to_complete'), 10, 3);

			//add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ) );

			add_action( 'woocommerce_order_status_completed', array($this, 'update_order_status'), 10, 1);

			// We need custom JavaScript to obtain a token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			add_action( 'woocommerce_thankyou_tap', array( $this, 'tap_thank_you_page' ) );
			add_action( 'woocommerce_receipt_tap', array( $this, 'tap_checkout_receipt_page' ) );
			add_action( 'woocommerce_api_tap_webhook', array( $this, 'webhook' ) );
			//add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		}

        // Set Here the WooCommerce icon for your action button

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

public function tap_thank_you_page($order_id) {
 			global $woocommerce;
 			$order = wc_get_order( $order_id );
 			if (empty($_GET['tap_id'])) {
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
 					//$this->$redirect_url;
 			}
 			if ($this->testmode == true) {

	 				$active_sk = $this->test_secret_key;

	 				$active_pk = $this->test_public_key;

	 			}



	 			else {

	 				$active_sk = $this->live_secret_key;

	 				$active_pk = $this->live_public_key;
	 			}

 			
 			if (!empty($_GET['tap_id'])) {
 				//echo "yest";exit;
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
				//echo '<pre>';print_r($response);exit;
				// if ($response->status == 'CAPTURED'){
				// 	wc_add_notice( __('Thank you for shopping with us. Your account has been charged and your transaction is successful. ', 'woothemes') . $error_message, 'error' );
    //                return;
				// }
				//var_dump($reposnse->status);exit;
				if ($response->status == 'CAPTURED') {
					$order->update_status('processing');
					$order->payment_complete($_GET['tap_id']);
					add_post_meta( $order->id, '_transaction_id', $_GET['tap_id'], true );
					
					$order->add_order_note(sanitize_text_field('Tap payment successful').("<br>").('ID').(':'). ($_GET['tap_id'].("<br>").('Payment Type :') . ($response->source->payment_method).("<br>").('Payment Ref:'). ($response->reference->payment)));
					$order->payment_complete($_GET['tap_id']);
					$woocommerce->cart->empty_cart();
					// $url=$this->get_return_url($order);
					// wp_redirect($url);
				    return;


				}
				if ($response->status !== 'CAPTURED'){
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
 					// $cart_url = $woocommerce->cart->get_cart_url();
 					// 	wp_redirect($cart_url);
 					//$this->$redirect_url;
 					$failure_url = get_permalink($this->failer_page_id);
 					wp_redirect($failure_url);
 					wc_add_notice( __('Transaction Failed ', 'woothemes') . $error_message, 'error' );
                   return;
 					exit;


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
public function tap_checkout_receipt_page($order_id) {
 			global $woocommerce,$wpdb;
	 			//var_dump($_POST);exit;
 			
	 			//$token = new WC_Payment_Token_CC();
	 		//	echo "<pre>";print_r($_POST);exit;
	 	    //var_dump(WC()->session->get( 'tap-woo-token' ));exit;
	 			$wooToken = WC()->session->get( 'tap-woo-token' );
	 			//var_dump($wooToken);exit;
	 			//echo $wooToken;exit;
	 			$order = wc_get_order($order_id);
                $currencyCode = $order->get_currency();
                $orderid = $order->get_id();
	 			$order_amount = $order->get_total();
	 			$table_prefix = $wpdb->prefix;
	 			
                if ($this->testmode == true) {

	 				$active_sk = $this->test_secret_key;

	 				$active_pk = $this->test_public_key;

	 			}



	 			else {

	 				$active_sk = $this->live_secret_key;

	 				$active_pk = $this->live_public_key;
	 			}

               
                $charge_url = 'https://api.tap.company/v2/charges';

                $first_name = $order->billing_first_name;

                $last_name = $order->billing_last_name;

                $return_url = $this->get_return_url($order); 

                $billing_email = $order->billing_email;

                $biliing_fone = $order->billing_phone;

                $order_amount = $order->get_total();

  
        			
        			$trans_object["amount"]                   = $order_amount;
        			$trans_object["currency"]                 = $currencyCode;
        			$trans_object["threeDsecure"]             = true;
        			$trans_object["save_card"]                = false;
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
        			$trans_object["customer"]["phone"]["country_code"]       = '965';
        			$trans_object["customer"]["phone"]["number"] = $biliing_fone;
        			$trans_object["source"]["id"] = $wooToken;
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
				$response = json_decode($response);
			
        $url=$response->transaction->url;
				wp_redirect($url);
				exit;
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


								'default'     => 'Pay with your credit card via Tap payment gateway. On clicking Place order payment will be processed.' ,

							),



					'testmode' => array(

								'title'       => 'Test mode',

								'label'       => 'Enable Test Mode',

								'type'        => 'checkbox',

								'description' => 'Place the payment gateway in test mode using test API keys.',

								'default'     => 'yes',

								'desc_tip'    => true,

							),

					// 'api_key' => array(

					// 			'title'       => 'APIKey',

					// 			'type'        => 'text'

					// 		),


          'test_secret_key' => array(

                'title'       => 'Test Secret Key',

                'type'        => 'text'

              ),

          'test_public_key' => array(

                'title'       => 'Test Public Key',

                'type'        => 'text'

              ),

          'live_public_key' => array(

                'title'       => 'Live Public Key',

                'type'        => 'text'

              ),

          'live_secret_key' => array(

                'title'       => 'Live Secret Key',

                'type'        => 'text'

              ),

					// 'payment_mode' => array(

		   //              'title'       => 'Payment Mode',

		   //              'type'        => 'select',

		   //              'class'       => 'wc-enhanced-select',

		   //              'default'     => '',

		   //              'desc_tip'    => true,

		   //              'options'     => array(

					// 					'charge'       => 'Charge',

					// 					'authorize'    => 'Authorize',

			 	// 						)

			 	// 	),



     //            	'save_card' => array(

					// 	'title'       => 'Save Cards',

					// 	'label'		=> 'Check if you want to save card data',

					// 	'type'        => 'checkbox',

					// 	'description' => '',

					// 	'default'     => 'no'

					// ),

				);

	 		}


			public function admin_scripts(){
 			 wp_enqueue_script('tap2', plugin_dir_url(__FILE__) . '/tap2.js');
 		}
        public function payment_scripts() {

	 		echo '<script type="text/javascript">var WEB_URL = "' . home_url('/') . '";</script>';
 
			wp_enqueue_script( 'tap_js', '//cdnjs.cloudflare.com/ajax/libs/bluebird/3.3.4/bluebird.min.js' );
			wp_enqueue_script( 'tap_js2', '//secure.gosell.io/js/sdk/tapjsli.js' );
 			//wp_register_script( 'tap_js2', plugins_url( 'tap2.js', __FILE__ ), array( 'jquery', 'tap_js' ) );
			// and this is our custom JS in your plugin directory that works with token.js
			wp_register_script( 'woocommerce_tap', plugins_url( 'tap.js', __FILE__ ), array( 'jquery', 'tap_js' ) );
			//wp_enqueue_script( 'tap2_js', plugins_url( 'tap2.js', __FILE__ ));

 
				// in most payment processors you have to use PUBLIC KEY to obtain a token
				wp_localize_script( 'woocommerce_tap', 'tap_params', array(
						'publishableKey' => $this->live_public_key
					) );
 //var_dump($publishableKey);exit;
				wp_enqueue_script( 'woocommerce_tap' );

 
	 	}

public function payment_fields() {
	
					
			// ok, let's display some description before the payment form
			if ( $this->description ) {
				// you can instructions for test mode, I mean test card numbers etc.
				if ( $this->testmode ) {
					$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank">documentation</a>.';
					$this->description  = trim( $this->description );
				}
			// display the description with <p> tags etc.
				echo wpautop( wp_kses_post( $this->description ) );
			}
				
		// I will echo() the form, but you can close PHP tags and print it directly in HTML
			echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
 
			// Add this action hook if you want your custom gateway to support it
			do_action( 'woocommerce_credit_card_form_start', $this->id );
 			
			// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
			echo '<form id="form-container" method="post" action="">
          			<!-- Tap element will be here -->
          			<div id="element-container"></div>  
          			<div id="error-handler" role="alert"></div>
          			<div id="success"style="color:#eeeeee;">
                    <span id="token"></span>

                       </div>
          			<!-- Tap pay button -->
          			<button id="tap-btn"style="display: none;
visibility: hidden;">Submit</button>
      			'.'
      			
          			 <input type="hidden" id="publishable_key" value="' . $this->live_public_key . '" />
          			 <input type="hidden" id="test_public_key" value="' . $this->test_public_key . '" />
          			 <input type="hidden" id="testmode" value="' . $this->testmode . '" />


          

				
      			</form>';
			do_action( 'woocommerce_credit_card_form_end', $this->id );
			

			echo '<div class="clear"></div></fieldset>';

 

		}


	 		public function process_payment($order_id) {
	 			global $woocommerce;
			$order 	= new WC_Order( $order_id );
			$token = new WC_Payment_Token_CC();
// 	 		//	echo "<pre>";print_r($_POST);exit;
	 			$wooToken = $_POST['tap-woo-token'];
	 			if($wooToken == ''){
                  $cart_url = $woocommerce->cart->get_cart_url();
 					 	wp_redirect($cart_url);
	 			}
	 			WC()->session->set('tap-woo-token', $wooToken);
	 			//sleep(02);
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
              


        
				}
			
				

				
			

				
				


        		}









			}















