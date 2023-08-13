<?php
/*
 * Plugin Name: WooCommerce Tap Payment Gateway
 * Plugin URI: https://tap.com/woocommerce/payment-gateway-plugin.html
 * Description: Take credit card payments on your store. (Features : PopUp & Redirect Both)
 * Author: Waqas Zeeshan
 * Author URI: http://tap.company
 * Version: 2.0

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
*/
add_filter( 'woocommerce_payment_gateways', 'tap_add_gateway_class');
function tap_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Tap_Gateway'; // your class name is here
	return $gateways;
}


add_action( 'plugins_loaded', 'tap_init_gateway_class' );
define('tap_imgdir', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function tap_init_gateway_class() {
	if(!class_exists('WC_Payment_Gateway')) return;

    if( isset($_GET['msg']) && !empty($_GET['msg']) ){
        add_action('the_content', 'tap_showMessage');
    }
    function tap_showMessage($content){
        return '<div class="'.htmlentities(sanitize_text_field($_GET['type'])).'">'.htmlentities(urldecode(sanitize_text_field($_GET['msg']))).'</div>'.$content;
    }
	
	class WC_Tap_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {

			$this->id = 'tap'; // payment gateway plugin ID
			$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'Tap Gateway';
			$this->method_description = 'Description of Tap payment gateway'; // will be displayed on  the options page
			$this->supports = array(
				'products',
				'refunds'
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
			$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
			$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );

			$this->payment_mode = $this->get_option('payment_mode');
			$this->ui_mode = $this->get_option('ui_mode');
			$this->post_url = $this->get_option('post_url');
			$this->save_card = $this->get_option('save_card');
			
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ), 11);
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_tap_webhook', array( $this, 'webhook' ) );
			add_action( 'woocommerce_receipt_tap', array( $this, 'tap_checkout_receipt_page' ) );
			add_action( 'woocommerce_thankyou_tap', array( $this, 'tap_thank_you_page' ));
 		}
 		
 		public function webhook($order_id) {
            
            $data = json_decode(file_get_contents("php://input"), true);
            $headers = apache_request_headers();
            $orderid = $data['reference']['order'];
            $status  = $data['status'];
            $charge_id = $data['id'];
            
            if ($status == 'CAPTURED') {
             $order = wc_get_order($orderid);
	         $order->payment_complete();
	         $order->add_order_note(sanitize_text_field('Tap payment successful').("<br>").('ID').(':'). ($charge_id.("<br>").('Payment Type :') . ($data['source']['payment_method']).("<br>").('Payment Ref:'). ($data['reference']['payment'])));
	         $order->reduce_order_stock();
	         update_option('webhook_debug', $_GET);
            }

            if($status == 'DECLINED'){
               $order = wc_get_order($orderid);
               $order->update_status('pending');
	           $order->add_order_note(sanitize_text_field('Tap payment failed').("<br>").('ID').(':'). ($charge_id.("<br>").('Payment Type :') . ($data['source']['payment_method']).("<br>").('Payment Ref:'). ($data['reference']['payment'])));
	           update_option('webhook_debug', $_GET);
	           	
            }
    	}

 		public function tap_thank_you_page($order_id){
 			
 			global $woocommerce;
 			$order = wc_get_order($order_id);
 			if(empty($_GET['tap_id'])){
 				$items = $order->get_items();
 					foreach($items as $item){
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
					    "authorization: Bearer ".$this->private_key,
					    "content-type: application/json"
			  		),
				));
				$response = curl_exec($curl);
				$response = json_decode($response);
				
				if ($response->status == 'CAPTURED' && $response->post->status == 'ERROR'){
					$order->update_status('processing');
					$order->payment_complete($_GET['tap_id']);
					add_post_meta( $order->id, '_transaction_id', $_GET['tap_id'], true );
					
					$order->add_order_note(sanitize_text_field('Tap payment successful').("<br>").('ID').(':'). ($_GET['tap_id'].("<br>").('Payment Type :') . ($response->source->payment_method).("<br>").('Payment Ref:'). ($response->reference->payment)));
					$order->payment_complete($_GET['tap_id']);
					$woocommerce->cart->empty_cart();

					if ($this->success_page_id == "" || $this->success_page_id == 0){
						$redirect_url = $order->get_checkout_order_received_url();
					}else{
						$redirect_url = get_permalink($this->success_page_id);
						wp_redirect($redirect_url);exit;
					}
				}

				if($response->status !== 'CAPTURED' && $response->post->status == 'ERROR'){
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

				if ($err) {
					echo "cURL Error #:" . $err;
				} 
 			}

 			if ($response->status !== 'CAPTURED' && $response->post->status == 'SUCCESS'){
 				$items = $order->get_items();
				foreach($items as $item){
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
 			
 			if(!empty($_GET['tap_id']) && $this->payment_mode == 'authorize'){
 				
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
					    "authorization: Bearer ".$this->private_key,
					    "content-type: application/json"
			  		),
				));
				$response = curl_exec($curl);
				$response = json_decode($response);
				if ($response->status == 'AUTHORIZED') {
					$order->update_status('pending');
					
					$order->add_order_note(sanitize_text_field('Tap payment successful').("<br>").('ID').(':'). ($_GET['tap_id'].("<br>").('Payment Type :') . ($response->source->payment_method).("<br>").('Payment Ref:'). ($response->reference->payment)));
					$woocommerce->cart->empty_cart(); 
				}
				else {
					$order->update_status('pending');
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
 			
 			global $woocommerce;
 			
 			$order = wc_get_order( $order_id );
 			$items = $order->get_items();
 			$items = array_values(($items));
 			$Post_Url = $this->post_url;

 			$order = wc_get_order($order_id);
            if($order && !is_wp_error($order)) {
            	$order_key = $order->get_order_key();
            }

            $ServerNotificationUrl=$Post_Url."/wc-api/tap_webhook";
            
 			$order_des = ('Order ID:'). ($order->id);
 			echo '<div id="tap_root"></div>';
 			echo '<input type="hidden" id="tap_end_url" value="' . $this->get_return_url($order) . '" />';
 			echo '<input type="hidden" id="ServerNotificationUrl" value="' . $ServerNotificationUrl . '" />';
 			echo '<input type="hidden" id="chg" value="' . $this->payment_mode . '" />';
 			echo '<input type="hidden" id="order_id" value="' . $order->id . '" />';
 			echo '<input type="hidden" id="order_des" value="' . $order_des  . '" />';
 			echo '<input type="hidden" id="save_card" value="' . $this->save_card . '" />';
 			echo '<input type="hidden" id="publishable_key" value="' . $this->publishable_key . '" />';
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
                $post_id = $item['product_id'];
		        $product = wc_get_product( $post_id );
		        $unit_price =   $product->get_price();
		        $unit_price = number_format((float)$unit_price, 3, '.', '');
		        $product_total_amount = $item['quantity']*$unit_price;
		        $product_total_amount =number_format((float)$product_total_amount, 3, '.', '');

 			    echo '<input type="hidden" name="items_bulk[]" data-name="'.$product->get_name().'" data-quantity="'.$item['quantity'].'" data-sale-price="'.$unit_price.'" data-item-product-id="'.$item['product_id'].'" data-product-total-amount="'.$product_total_amount.'" class="items_bulk">';
 			}
 			
 			if ($this->ui_mode == 'redirect') {
 				?>	
             	<script type="text/javascript">
              		window.onload=function(){ 
              			goSell.openPaymentPage();
                	};
             	</script>
            <?php
 				echo '<input type="button" value="Place order by Tap" id="submit_tap_payment_form" onclick="goSell.openPaymentPage()" />';

 			}
 			if ($this->ui_mode == 'popup') {
 				?>	
             	<script type="text/javascript">
             		jQuery(function(){
						jQuery("#submit_tap_payment_form").click();
					});
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
					'default'     => 'Pay with your credit card via Tap payment gateway. On clicking Pay by Tap button popup will load.' ,
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'test_publishable_key' => array(
					'title'       => 'Test Publishable Key',
					'type'        => 'text'
				),
				'test_private_key' => array(
					'title'       => 'Test Private Key',
					'type'        => 'password',
				),
				'publishable_key' => array(
					'title'       => 'Live Publishable Key',
					'type'        => 'text'
				),
				'private_key' => array(
					'title'       => 'Live Private Key',
					'type'        => 'password'
				),
				'payment_mode' => array(
	                'title'       => 'Payment Mode',
	                'type'        => 'select',
	                'class'       => 'wc-enhanced-select',
	                'default'     => '',
	                'desc_tip'    => true,
	                'options'     => array('charge' => 'Charge', 'authorize'    => 'Authorize')
		 		),
		 		'ui_mode' => array(
	                'title'       => 'Ui Mode',
	                'type'        => 'select',
	                'class'       => 'wc-enhanced-select',
	                'default'     => '',
	                'desc_tip'    => true,
	                'options'     => array('redirect' => 'redirect', 'popup' => 'popup')
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
					'title'       => 'Website URL',
					'type'        => 'text'
				),			
	        	'save_card' => array(
					'title'       => 'Save Cards',
					'label'		=> 'Check if you want to save card data',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				)
			);
	 	}
	 			
	 	public function payment_scripts(){
	 		wp_register_style( 'tap_payment',  plugins_url('tap-payment.css', __FILE__));
	 		wp_enqueue_style('tap_payment');
	 		wp_register_style( 'tap_style', '//goSellJSLib.b-cdn.net/v1.6.1/css/gosell.css' );
	 		wp_register_style( 'tap_icon', '//goSellJSLib.b-cdn.net/v1.6.1/imgs/tap-favicon.ico' );
			wp_enqueue_style('tap_style');
			wp_enqueue_style('tap_icon');
			wp_enqueue_script( 'tap_js', '//goSellJSLib.b-cdn.net/v1.6.1/js/gosell.js', array('jquery') );

			wp_register_script( 'woocommerce_tap', plugins_url( 'tap.js', __FILE__ ), 'gosell');
			wp_enqueue_style( 'tap-payment', plugins_url( 'tap-payment.css', __FILE__ ) );
			wp_enqueue_script( 'woocommerce_tap' );	
	 	}

 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
			global $woocommerce;

			$customer_user_id = get_current_user_id();
			$currency = get_woocommerce_currency();
			$amount   = $woocommerce->cart->total;
			$mode     = $this->payment_mode;
 			if($this->description){
				if ($this->testmode){
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
			do_action('woocommerce_credit_card_form_end', $this->id );
			echo '<div class="clear"></div></fieldset>';
		}

		public function process_payment($order_id){
			global $woocommerce;
			$order 	= new WC_Order( $order_id );
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
	 	}

	 	public function process_refund($order_id, $amount = null, $reason = ''){
			global $post, $woocommerce;

			$order = new WC_Order($order_id);
			$transID = $order->get_transaction_id();
			$currency = $order->currency;
	 		$refund_url = 'https://api.tap.company/v2/refunds';
	 		$refund_request['charge_id'] = $transID;
	 		$refund_request['amount'] = $amount;
	 		$refund_request['currency'] = $currency;
	 		$refund_request['description'] = "Description";
	 		$refund_request['reason'] = $reason;
	 		$json_request = json_encode($refund_request);

	 		$curl = curl_init();
	 		curl_setopt_array($curl, array(
  					CURLOPT_URL => "https://api.tap.company/v2/refunds",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => $json_request,
					CURLOPT_HTTPHEADER => array(
				    		"authorization: Bearer ".$this->private_key,
				    		"content-type: application/json"
			  		),
				)
	 		);

	 		$response = curl_exec($curl);
	 		$response = json_decode($response);
	 		if ($response->id){
	 			if ($response->status == 'PENDING'){
	 				$order->add_order_note(sanitize_text_field('Tap Refund successful').("<br>").'Refund ID'.("<br>"). $response->id);
	 				return true;
	 			}
	 		}
	 		else { 
	 			return false;
	 		}
		}


		function tap_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages
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
 	}
}
