<?php



/*



 * Plugin Name: WooCommerce Tap Payment Gateway



 * Plugin URI: 



 * Description: Take credit card payments on your store.



 * Author: Waqas Zeeshan



 * Author URI: https://tap.company/



 * Version: 2.1.2











 /*



 * This action hook registers our PHP class as a WooCommerce payment gateway



 */



add_filter( 'woocommerce_payment_gateways', 'tap_add_gateway_class' );



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
         $this->icon = tap_imgdir . 'logo.png';
			$this->title = $this->get_option( 'title' );
		   $this->failer_page_id = $this->settings['failer_page_id'];
			$this->success_page_id = $this->settings['success_page_id'];
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
			$this->ui_mode = $this->get_option('ui_mode');
			$this->ui_language = $this->get_option('ui_language');
         $this->post_url = $this->get_option('post_url');
         $this->shopward_shipping = $this->get_option('shopward_shipping');
         $this->knet = $this->get_option('tap_knet');
         $this->benefit = $this->get_option('tap_benefit');
			$this->save_card = $this->get_option('save_card');
			
			if ($this->ui_mode == 'tokenization') {
				add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ) );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

				add_filter( 'woocommerce_admin_order_actions', 'add_custom_order_status_actions_button', 100, 2 );
				add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ) );

				add_action( 'woocommerce_order_status_completed', array($this, 'update_order_status'), 10, 1);


				add_filter( 'woocommerce_order_actions', array( $this, 'add_capture_charge_order_action' ) );

				// We need custom JavaScript to obtain a token
				add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ), 11);
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ), 11);
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			
			add_action( 'woocommerce_receipt_tap', array( $this, 'tap_checkout_receipt_page' ));
			add_action( 'woocommerce_thankyou_tap', array( $this, 'tap_thank_you_page' ) );
			add_action( 'woocommerce_api_tap_webhook', array( $this, 'webhook' ) );

			
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
			$active_sk = '';
			if ($this->testmode == '1') {
	 			$active_sk = $this->test_secret_key;
			}
			else {
	 			$active_sk = $this->live_secret_key;
			}
			if ($this->ui_mode == 'popup' || $this->ui_mode == 'redirect' || $this->ui_mode == 'tokenization' ){
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
		 			//echo $cart_url;exit;
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
				
				if ($response->status == 'CAPTURED' && !empty($response->customer->id) && !empty($response->card->id)) {
					$curl = curl_init();
			 		curl_setopt_array($curl, array(
		  			CURLOPT_URL => "https://api.tap.company/v2/card/".$response->customer->id."/".$response->card->id,
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
					$response2 = curl_exec($curl);
					$response2 = json_decode($response2);
					if (!empty($response->card->id)) {
						$last_four = $response2->last_four;
	               $tap_brand = $response2->brand;
	               $tap_exp_month = $response2->exp_month;
	               $tap_exp_year = $response2->exp_year;
	        			$token_recieved = $response->source->id;
	        			$dt = DateTime::createFromFormat('y', $tap_exp_year);
						$token = new WC_Payment_Token_CC();
						$token->set_token($token_recieved); // Token comes from payment processor.
						$token->set_gateway_id($this->id);
						$token->set_last4($last_four);
						$token->set_expiry_year($dt->format('Y'));
						$token->set_expiry_month($tap_exp_month);
						$token->set_card_type($tap_brand);
						$token->set_user_id(get_current_user_id());
						$token->save();
					
						if ($this->testmode == '1') {
							$exists = get_user_meta(get_current_user_id(), '_test_tap_customer_id_');
						}
						else {
							$exists = get_user_meta(get_current_user_id(), '_live_tap_customer_id_');
						}

						if (empty($exists[0])) {
							if ($this->testmode == '1') {
								add_user_meta(get_current_user_id(), '_test_tap_customer_id_', $response->customer->id);
							}
							else {
								add_user_meta(get_current_user_id(), '_live_tap_customer_id_', $response->customer->id);
							}
					
						}
					}

				}

				if ($response->status == 'CAPTURED'  && $response->post->status == 'ERROR' ) {
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
			
				if ($response->status !== 'CAPTURED' && $response->post->status == 'ERROR') {
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
 				//echo "authorize";exit;
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
				}
				else {
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
 			//echo 'sldkaslkd';exit;
 			$items = WC()->cart->get_cart();
 			$items = array_values(($items));
 			$order = wc_get_order( $order_id );
 			if($this->testmode == "testmode"){
                $active_pk = $this->test_public_key;
                $active_sk = $this->test_secret_key;
 			}
 			else{
 				$active_pk = $this->live_public_key;
 				$active_sk = $this->live_secret_key;
 			}
 			
 			$ref = '';
 			if($order->currency=="KWD"){
				$Total_price = number_format((float)$order->total, 3, '.', '');
			}
			else{
				$Total_price = number_format((float)$order->total, 2, '.', '');
			}
            $Hash = 'x_publickey'. $active_pk.'x_amount'.$Total_price.'x_currency'.$order->currency.'x_transaction'.$ref.'x_post'.get_site_url()."/wc-api/tap_webhook";
            $hashstring = hash_hmac('sha256', $Hash, $active_sk);
        	$exists = get_user_meta(get_current_user_id(), '_tap_customer_id_');
			$tap_customer = $exists[0];

 			echo '<div id="tap_root"></div>';
 			echo '<input type="hidden" id="publishable_key" value="' . $this->live_public_key . '" />';
         echo '<input type="hidden" id="test_public_key" value="' . $this->test_public_key . '" />';
         echo '<input type="hidden" id="testmode" value="' . $this->testmode . '" />';
         echo '<input type="hidden" id="post_url" value="' . get_site_url()."/wc-api/tap_webhook" . '" />';
 			echo '<input type="hidden" id="tap_end_url" value="' . $this->get_return_url($order) . '" />';
 			echo '<input type="hidden" id="order_id" value="' . $order->id . '" />';
 			echo '<input type="hidden" id="hashstring" value="' . $hashstring . '" />';
 			echo '<input type="hidden" id="ui_language" value="' . $this->ui_language . '" />';
 			echo '<input type="hidden" id="tap_customer_id" value="' .  $tap_customer . '" />';
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
 				echo '<input type="hidden" name="items_bulk[]" data-name="'.$item['data']->name.'" data-quantity="'.$item['quantity'].'" data-sale-price="'.$item['data']->sale_price.'" data-item-product-id="'.$item['product_id'].'" data-product-total-amount="'.$item['quantity']*$item['data']->sale_price.'" class="items_bulk">';
 			}
 			if ( $this->ui_mode == 'redirect') {
 				?>	
             	<script type="text/javascript">
              		window.onload=function(){ 
              			goSell.openPaymentPage();
                	};
             	</script>
            <?php
 				echo '<input type="button" value="Place order by Tap" id="submit_tap_payment_form" onclick="goSell.openPaymentPage()" />';

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
							'title' => 'Description',
							'type' => 'textarea',
							'description' => 'This controls the description which the user sees during checkout.',
								'default' => 'Pay with your credit card via Tap payment gateway. On clicking Place order payment will be processed.' ,
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
	                	'title'    => 'Test Secret Key',
	                	'type'     => 'text'
              			),

          		'test_public_key' => array(
		               'title'    => 'Test Public Key',
		               'type'     => 'text'
              			),

          		'live_public_key' => array(
                		'title'    => 'Live Public Key',
                		'type'     => 'text'
              	),
          		'live_secret_key' => array(
                	'title'       => 'Live Secret Key',
                	'type'        => 'text'
              	),

					'payment_mode' => array(
		            'title'       => 'Payment Mode',
		            'type'        => 'select',
		            'class'       => 'wc-enhanced-select',
		            'default'     => '',
		            'desc_tip'    => true,
		            'options'     => array(
								'charge'       => 'Charge',
								'authorize'    => 'Authorize',
			 			)

			 		),
			 			
			 		'ui_mode' => array(
		            'title'       => 'Ui Mode',
		            'type'        => 'select',
		            'class'       => 'wc-enhanced-select',
		            'default'     => '',
		            'desc_tip'    => true,
		            'options'     => array(
								'redirect'    => 'redirect',
								'popup'       => 'popup',
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
								'english'    => 'English',
								'arabic'       => 'Arabic',
											
			 			)
			 		),

			 		'failer_page_id' => array(
						'title' 		=> __('Return to failure Page'),
						'type' 			=> 'select',
						'options' 		=> $this->tap_get_pages('Select Page'),
						'description' 	=> __('URL of failure page', 'kdc'),
						'desc_tip' 		=> true
                ),
			 		'success_page_id' => array(
						'title' 		=> __('Return to success Page'),
						'type' 			=> 'select',
						'options' 		=> $this->tap_get_pages('Select Page'),
						'description' 	=> __('URL of success page', 'kdc'),
						'desc_tip' 		=> true
                ),	

			 		'post_url' => array(
										'title'       => 'Post URL',
										'type'        => 'text'
									),
			 		'tap_knet' => array(
						'title'       => 'Knet ',
						'label'		=> 'Check if you want t enable Knet at checkout',
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no'
					),
			 	// 	'shopward_shipping' => array(
					// 	'title'       => 'Shopward Shipping',
					// 	'label'		=> 'Check if you want to enable Shopward shipping',
					// 	'type'        => 'checkbox',
					// 	'description' => '',
					// 	'default'     => 'no'
					// ),


			 		'save_card' => array(
						'title'       => 'Save Cards',
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
			if ($this->ui_mode == 'tokenization') {
	 			echo '<script type="text/javascript">var WEB_URL = "' . home_url('/') . '";</script>';
            wp_register_style( 'tap_payment',  plugins_url('tap-payment.css', __FILE__));
			   wp_enqueue_script( 'tap_js', 'https://cdnjs.cloudflare.com/ajax/libs/bluebird/3.3.4/bluebird.min.js' );
			   wp_enqueue_script( 'tap_js2', 'https://secure.gosell.io/js/sdk/tapjsli.js' );
				wp_register_script( 'woocommerce_tap', plugins_url( 'tap.js', __FILE__ ), array( 'jquery', 'tap_js' ) );
				wp_localize_script( 'woocommerce_tap', 'tap_params', array(
						'publishableKey' => $this->live_public_key
					) );
				wp_enqueue_style( 'tap-payment', plugins_url( 'tap-payment.css', __FILE__ ) );
				wp_enqueue_script( 'woocommerce_tap' );
			}
			if ($this->ui_mode == 'popup' || $this->ui_mode == 'redirect' ) {
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
			if ($this->ui_mode == 'tokenization') {
				$current_user = get_current_user_id();

				if ($current_user) {
					$save_card_feild = '';
				}
				else {
					$save_card_feild = 'disabled';
					$guest_user = "checked";
					
				}
				
				$all_tokens = WC_Payment_Tokens::get_customer_tokens( $current_user);
				if ($this->testmode == 1 ) {
					$tap_customer = get_user_meta(get_current_user_id(), '_test_tap_customer_id_');
					$tap_customer = $tap_customer[0];
				}
				else {
					$tap_customer = get_user_meta(get_current_user_id(), '_live_tap_customer_id_');
					$tap_customer = $tap_customer[0];
				}
				//echo $this->knet;exit;
				echo '<input type="hidden" value='.$current_user.' id="is_user_logged_in"';
				echo '<input type="hidden" value='.$tap_customer.' id="customer">';
				echo '<ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods">';
				foreach($all_tokens as $key=>$tok) {
					if ($tok->get_is_default() == true) {
						$checked = "selected";
					}
					else {
						$checked ="";
					}
					echo '<li class="woocommerce-SavedPaymentMethods-token"><input type="radio" name="woocommerce-SavedPaymentMethods-token" value='.$tok->get_id().' checked="'.$checked.'">'.$tok->get_card_type().'************'.$tok->get_last4().'</li>';
				}
				echo '<p class="form-row woocommerce-SavedPaymentMethods-saveNew woocommerce-validated">
					<li class="woocommerce-SavedPaymentMethods-new id="tap_new"><input type="radio" name="woocommerce-SavedPaymentMethods-token" value="new"; "class="woocommerce-SavedPaymentMethods-tokenInput" id="new_val" checked="'.$guest_user.'"><span id="new_payment_method">New Payment Method</span></li>';
				echo '</ul>';
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
      				echo '<div class="form-row form-row-last">

				<input id="wc-tap-new-payment-method" name="wc-tap-save-card" type="checkbox" value="true" class="form-row woocommerce-SavedPaymentMethods-saveNew woocommerce-validated" style="width:auto;"'. $save_card_feild.' >

				<label for="wc-tap-new-payment-method" style="display:inline;">Save Card</label>

				</div>'. '<div></br>' .
				'<ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods">'; 
					?>
					<?php 

						if ($this->knet == 'yes') {
							echo '<li>'.'<input type="radio" id="tap_knet" name="tap_knet" value="tap_knet">'.'KNET'.'</li>'. '' ;
						}
						if ($this->benefit == 'yes') {
							//echo $this->benefit;exit;
							echo '<li>'.'<input type="radio" id="tap_benefit" name="tap_benefit" value="tap_benefit">'.'BENEFIT'.'</li>';
						}
					?>
					<?php

				'</ul>'.
				'</div>';

					

			}
			if ($this->ui_mode == 'popup' || $this->ui_mode == 'redirect' ) {
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

 			<div id="tap_root">
 			</div>
 			<?php
			do_action( 'woocommerce_credit_card_form_end', $this->id );
		
			echo '<div class="clear"></div></fieldset>';
 			}	

		}

    

	 	public function process_payment($order_id) {
	 		if ($this->ui_mode == 'popup' || $this->ui_mode == 'redirect' ) {
	 			global $woocommerce;
				$order = new WC_Order( $order_id );
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				);
			}
			if ($this->ui_mode == 'tokenization') {
					global $woocommerce,$wpdb;
		 			$wooToken = $_POST['tap-woo-token'];
		 			$save_card_value = $_POST['wc-tap-save-card'];
		 			if ($save_card_value == true) {
		 				$save_card = true;
		 			}
		 			else {
		 				$save_card = false;
		 			}
		 			$order = wc_get_order($order_id);
	            $currencyCode = $order->get_currency();
	            $orderid = $order->get_id();
		 			$order_amount = $order->get_total();
		 			$table_prefix = $wpdb->prefix;
		 			
	            if ($this->testmode == "testmode") {
	                $active_pk = $this->test_public_key;
	                $active_sk = $this->test_secret_key;
	 				}
	 				else {
	 					$active_pk = $this->live_public_key;
	 					$active_sk = $this->live_secret_key;
	 				}
	 				$showard_shipping = $this->shopward_shipping;
	 				$ref = '';
	 				if ($currencyCode=="KWD") {
						$order_amount = number_format((float)$order->get_total(), 3, '.', '');
			   	}
			    	else{
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
	          	if ($this->testmode	== 'yes') {
	            	$existing_tap_customer = get_user_meta(get_current_user_id(), '_test_tap_customer_id_');
	         	}
	         	else {
	         		$existing_tap_customer = get_user_meta(get_current_user_id(), '_live_tap_customer_id_');
	         	}
	         	$knet = $_POST['tap_knet'];
	         	$benefit = $_POST['tap_benefit'];
	         	if (!empty($knet))
		            if (!empty($existing_tap_customer[0])) {
		            	$source = 'src_kw.knet';
		            	$trans_object["customer"]["id"] = $existing_tap_customer[0];
		            }
		         }
		      	if (!empty($benefit)) {
		      		$source = 'src_bh.benefit';
		      	}
	            //var_dump($existing_tap_customer[0]);exit;
	           //  if ($this->shopward_shipping == 'yes') {
		          //   $desination_amount = $order->get_shipping_total();
		          // 	$destinations = [
		          //   	'destination' => [
		          //   		[
				        //        'id' => '19094391',
				        //        'amount' => $desination_amount,
				        //        'currency' => $currencyCode
			         //    	]
		          //   	]
		        		// ];
		        		// $trans_object["destinations"] = $destinations;
		        	//}
		        	
		        	// echo $knet;exit;

		        	if ($knet == 'tap_knet' || $benefit == 'tap_benefit' ) {
		        		$trans_object["amount"]                   = $order_amount;
		        		$trans_object["currency"]                 = $currencyCode;
		        		$trans_object["threeDsecure"]             = true;
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
		        		$trans_object["customer"]["phone"]["country_code"]       = '';
		        		$trans_object["customer"]["phone"]["number"] = $biliing_fone;
		        		$trans_object["source"]["id"] = $source;
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
								//wp_redirect($redirect_url);exit;
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
					$new_card = $_POST['woocommerce-SavedPaymentMethods-token'];
					if ($new_card == 'new') {
		        		$trans_object["amount"]                   = $order_amount;
		        		$trans_object["currency"]                 = $currencyCode;
		        		$trans_object["threeDsecure"]             = true;
		        		$trans_object["save_card"]                = $save_card_value;
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

	            // Save the new token to the database
	                   
	        	if ($obj->transaction->url == '') {
		        	if ( $this->failer_page_id == "" || $this->failer_page_id == 0 ) {
						$failure_url =  $this->get_return_url($order);
					} else {
						$failure_url = get_permalink($this->failer_page_id);
						//wp_redirect($redirect_url);exit;
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
			else {
					$token_id = wc_clean( $_POST['woocommerce-SavedPaymentMethods-token'] );
					$token_saved  = WC_Payment_Tokens::get($token_id);
					$charge_token = $token_saved->get_token();

					$curl2 = curl_init();
        				curl_setopt_array($curl2, array(
            				CURLOPT_URL => "https://api.tap.company/v2/tokens/".$charge_token,
            				CURLOPT_RETURNTRANSFER => true,
            				CURLOPT_ENCODING => "",
            				CURLOPT_MAXREDIRS => 10,
            				CURLOPT_TIMEOUT => 30,
            				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            				CURLOPT_CUSTOMREQUEST => "GET",
            				// CURLOPT_POSTFIELDS => {},
            				CURLOPT_HTTPHEADER => array(
                          		"authorization: Bearer ".$active_sk,
                            	"content-type: application/json"
                                ),
       					));
        			$response3 = curl_exec($curl2);
        					$response3 = json_decode($response3);
        			$card_id = $response3->card->id;

        			if ($this->testmode	== 1) {
	        			$customer = get_user_meta(get_current_user_id(), '_test_tap_customer_id_');
	        			$customer_id = $customer[0];
	        		}
	        		else {
	        			$customer = get_user_meta(get_current_user_id(), '_live_tap_customer_id_');
	        			$customer_id = $customer[0];
	        		}

        			$saved_card = [
        				'saved_card' => [
        					'card_id' => $card_id,
        					'customer_id' => $customer_id
        				],
        				'client_ip' => '127.0.0.1'
        			];
        			$curl4 = curl_init();

					curl_setopt_array($curl4, array(
					  		CURLOPT_URL => "https://api.tap.company/v2/tokens",
					  		CURLOPT_RETURNTRANSFER => true,
					  		CURLOPT_ENCODING => "",
					  		CURLOPT_MAXREDIRS => 10,
					  		CURLOPT_TIMEOUT => 50,
					  		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  		CURLOPT_CUSTOMREQUEST => "POST",
					  		CURLOPT_POSTFIELDS => json_encode($saved_card),
					  CURLOPT_HTTPHEADER => array(
					    "authorization: Bearer ".$active_sk,
					    "content-type: application/json"
					  ),
					));

					$response4 = curl_exec($curl4);
        			$res = json_decode($response4);

        			$new_token = $res->id;
        		
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
        			$trans_object['card']['id']				=  $card_id;
        			$trans_object["customer"]["first_name"]    = $first_name;
        			$trans_object["customer"]["last_name"]    = $last_name;
        			$trans_object["customer"]["email"]        = $billing_email;
        			$trans_object["customer"]["phone"]["country_code"]       = '';
        			$trans_object["customer"]["phone"]["number"] = $biliing_fone;
        			$trans_object["source"]["id"] = $new_token;
        			$trans_object["post"]["url"] = get_site_url().'/wc-api/tap_webhook';
        			$trans_object["redirect"]["url"] = $return_url;
        			$frequest = json_encode($trans_object);
        			$frequest = stripslashes($frequest);
        			$charge_url = 'https://api.tap.company/v2/charges';
        			//var_dump($frequest);exit;
        			$curl = curl_init();
        				curl_setopt_array($curl, array(
            				CURLOPT_URL => $charge_url,
            				CURLOPT_RETURNTRANSFER => true,
            				CURLOPT_ENCODING => "",
            				CURLOPT_MAXREDIRS => 10,
            				CURLOPT_TIMEOUT => 0,
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
        					return array(
							'result' => 'success',
							'redirect' => $response->transaction->url
							);	
	 			}
			}			
	}

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

   function process_refund($order_id, $amount = null, $reason = '') {
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
			)
		);

		$response = curl_exec($curl);
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
}

















