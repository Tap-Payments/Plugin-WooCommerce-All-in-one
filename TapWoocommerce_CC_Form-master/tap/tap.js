setTimeout(function(){
  var testmode = jQuery("#testmode").val();
 //alert(publishable_key);
if (testmode == true) {

          var active_pk = jQuery("#test_public_key").val();
        }
 else{
   var active_pk = jQuery("#publishable_key").val();
     }     
// jQuery('#payment').change(function(){
//         if(jQuery("#payment_method_tap").is(":checked")) {
//              jQuery('#place_order').prop('disabled', true);
             
//         }
//         else {
//             jQuery('#place_order').prop('disabled', false);
//         }
//     });
var tap = Tapjsli(active_pk);

var elements = tap.elements({});
var style = {
  base: {
    color: '#535353',
    lineHeight: '18px',
    fontFamily: 'sans-serif',
    fontSmoothing: 'antialiased',
    fontSize: '16px',
    '::placeholder': {
      color: 'rgba(0, 0, 0, 0.26)',
      fontSize:'15px'
    }
  },
  invalid: {
    color: 'red'
  }
};

var labels = {
    cardNumber:"Card Number",
    expirationDate:"MM/YY",
    cvv:"CVV",
    cardHolder:"Card Holder Name"
  };

var paymentOptions = {
  currencyCode:'all',
  labels : labels,
  TextDirection:'ltr',
  paymentAllowed: 'all',
}
console.log(paymentOptions);
var card = elements.create('card', {style: style},paymentOptions);

card.mount('#element-container');

card.addEventListener('change', function(event) {
 console.log(event)
 
 
      if(event.code == '200' ){
        
        jQuery("#tap-btn").trigger("click");
       // event.stopImmediatePropagation();
    }
     

  if(event.BIN){
    console.log(event.BIN)
  }
  if(event.loaded){
    console.log("UI loaded :"+event.loaded);
    console.log("current currency is :"+card.getCurrency())
  }
  var displayError = document.getElementById('error-handler');
  if (event.error) {
    displayError.textContent = event.error.message;
  } else {
    displayError.textContent = '';
  }
});
// Handle form submission
var form = document.getElementById('form-container');
console.log(form);
if(form==null){
  return;
}
form.addEventListener('submit', function(event) {
  event.preventDefault();

  tap.createToken(card).then(function(result) {
    if (result.error) {
      // Inform the user if there was an error
      var errorElement = document.getElementById('error-handler');
      errorElement.textContent = result.error.message;
    } else { 
      // Send the token to your server
      var errorElement = document.getElementById('success');
      errorElement.style.display = "block";
      var tokenElement = document.getElementById('token');
      
      tokenElement.textContent = result.id;
      var tokan = result.id;

     // setTimeout(function(){
 //  if (tokan) {
 //    jQuery("#place_order").trigger("click");
 //  }
 // },1000);
      var forms = jQuery("#wc-tap-cc-form");
        // token contains id, last4, and card type
       var token = result.id;
       
       var parentOrderForm = jQuery('.woocommerce-checkout');
       var orderTokenInput = document.createElement("input");
       orderTokenInput.setAttribute("type", "hidden");
       orderTokenInput.setAttribute("name", "tap-woo-token");
       orderTokenInput.setAttribute("value", token);
       parentOrderForm.append(orderTokenInput);

        // insert the token into the form so it gets submitted to the server
        
        // // and submit
        // forms.get(0).submit();
        
        // var myURL = WEB_URL + 'index.php';
        // jQuery.ajax({
        //   type: 'POST',
        //   url: myURL,
        //   data: {'tokenID': token, 'after_token': true},
        //   success: function(response) {
        //     jQuery('#div').html(response);
        //     forms.append("<div>" + response + "</div");
        //   }
        // });
       
    }

  });
});




 },3000);
//var token = false;
 // setTimeout(function(){
 // jQuery( "#place_order" ).click(function( event ) {
 //  // if (token) {
 //  //       token = false; // reset flag
 //  //       return; // let the event bubble away
 //  //   }
 //    event.preventDefault();
 //    token = true;
 //    jQuery("#tap-btn").trigger("click");

    //return false;form.addEventListener('submit', function(event) {
            
              //event.preventDefault();
              //event.stopPropagation();
               // alert('To proceed Please click on Place order button'); 

 //prevents form from submitting

  // });
  // },1000);
 
// jQuery(function($){
 
//   var checkout_form = $( 'form.woocommerce-checkout' );
//   checkout_form.on( 'checkout_place_order', token);
 
// });

// var $pm_form  = $( 'form.checkout, form#order_review, form#add_payment_method' );
//     $( "#wc-rits_paymaya-cc-form" ).append( '<input type="hidden" class="rits_paymaya-token" name="rits_paymaya_token" value="' + result.id + '"/>' );
//     //console.log(paymentToken);
//     $pm_form.submit();

//   }
// jQuery(function($){
//  //alert(result.id);
//   var form$ = $( 'form.woocommerce-checkout' );
//  checkout_form.on( 'checkout_place_order', token);
 
// });