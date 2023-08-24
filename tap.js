function everyTime() {
    if(document.getElementById('form-container')==null){
    
    }else{
        var testmode = jQuery("#testmode").val();
        if(testmode == true) {
          var active_pk = jQuery("#test_public_key").val();
        }else{
          var active_pk = jQuery("#publishable_key").val();
    }
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
          var forms = jQuery("#wc-tap-cc-form");
          // token contains id, last4, and card type
          var token = result.id;
           
          var parentOrderForm = jQuery('.woocommerce-checkout');
          var orderTokenInput = document.createElement("input");
          orderTokenInput.setAttribute("type", "hidden");
          orderTokenInput.setAttribute("name", "tap-woo-token");
          orderTokenInput.setAttribute("value", token);
          parentOrderForm.append(orderTokenInput);
        }
      });
    });
    clearInterval(myInterval);
    }
}
var myInterval = setInterval(everyTime, 1000);