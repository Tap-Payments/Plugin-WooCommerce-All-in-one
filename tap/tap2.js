jQuery( document ).ready(function() {
    jQuery('#woocommerce_tap_test_publishable_key').show(); 
    jQuery('label[for=woocommerce_tap_test_publishable_key], input#a').show();
    jQuery('label[for=woocommerce_tap_test_private_key], input#a').show();
    jQuery('#woocommerce_tap_test_private_key ').show();
    jQuery('#woocommerce_tap_publishable_key').hide();
    jQuery('#woocommerce_tap_private_key').hide();
    jQuery('label[for=woocommerce_tap_publishable_key], input#a').hide();
    jQuery('label[for=woocommerce_tap_private_key], input#a').hide();
    jQuery('#woocommerce_tap_testmode').click(function(){
        jQuery('#woocommerce_tap_test_publishable_key').toggle();
        jQuery('#woocommerce_tap_test_private_key').toggle();
        jQuery('label[for=woocommerce_tap_test_publishable_key], input#a').toggle();
        jQuery('label[for=woocommerce_tap_test_private_key], input#a').toggle();
        jQuery('#woocommerce_tap_publishable_key').toggle();
        jQuery('#woocommerce_tap_private_key').toggle();
        jQuery('label[for=woocommerce_tap_publishable_key], input#a').toggle();
        jQuery('label[for=woocommerce_tap_private_key], input#a').toggle();
        });

});
