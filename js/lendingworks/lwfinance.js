window.LendingWorks = {} // global Object container; don't use var

var LendingWorksForm = new Class.create()
LendingWorksForm.prototype = {
  initialize: function (elem, methodCode) {
    this.code = methodCode;
  },

  createOrder: function (event) {
    checkout.setLoadWaiting('payment');
    currentStore = document.getElementById('payment_form_lwfinance').getAttribute('data-storeurl');
    new Ajax.Request(
        currentStore+'lwapi/order/createlworder', {
        method: 'post',

        onFailure: function (transport) {
        // display error message
        alert('Sorry - there has been an error connecting to Lending Works. Please contact the site administrator or try an alternative payment method.')
        }.bind(this),

        onSuccess: function (transport) {
        try {
          if (transport.responseJSON) {
            var response = transport.responseJSON

            if (response.error) {
              throw response
            } else if (response.ajaxExpired && response.ajaxRedirect) {
              setLocation(response.ajaxRedirect)
            }

            LendingWorks.token = response.token;
            LendingWorks.message = response.message;
            LendingWorks.script_url = response.script_url;
          }
        } catch (e) {
          alert(e.message)
        }
        }.bind(this),

        onComplete: function () {
        checkout.setLoadWaiting(false);
        },
        }
    )
  },

    overwritePlaceOrderButton: function () {
      if (payment.currentMethod === 'lwfinance') {
        var container = document.getElementById('review-buttons-container');
        var buttons = container.getElementsByTagName('button');
        for (var i = 0; i < buttons.length; i++) {
            buttons.item(i).style.display = 'none';
        }
      }else{
        document.getElementById('review-buttons-container-lendingworks').style.display = 'none';
      }
    },

  loadScript: function (source, beforeEl, async = true, defer = true) {
    return new Promise(
        (resolve, reject) => {
        let script = document.createElement('script')
        const prior = beforeEl || document.getElementsByTagName('script')[0]

        script.async = async
        script.defer = defer

        function onloadHander(_, isAbort) 
        {
        if (isAbort || !script.readyState || /loaded|complete/.test(script.readyState)) {
          script.onload = null
          script.onreadystatechange = null
          script = undefined

          if (isAbort) { reject() } else { resolve() }
        }
        }

        script.onload = onloadHander
        script.onreadystatechange = onloadHander

        script.src = source;
        document.head.appendChild(script);
        }
    )
  },

  placeOrder: function (event) {
    script_url = LendingWorks.script_url
    orderToken = LendingWorks.token
    currentStore = document.getElementById('payment_form_lwfinance').getAttribute('data-storeurl');
    this.loadScript(script_url).then(
        () => {
        var checkoutHandler = LendingWorksCheckout(
            orderToken,
            window.location.href,
            function (status, id) {
            if (['accepted', 'approved', 'referred'].indexOf(status) !== -1) {
              new Ajax.Request(
                  currentStore+'lwapi/order/addlworderdetails', {
                  method: 'POST',
                  parameters: {
                  lw_order_id: id,
                  lw_order_status: status,
                  },
                  onFailure: function (transport) {
                  // display error message
                  alert('Sorry - there has been an error placing the order. Please contact the site administrator.')
                  }.bind(this),

                  onSuccess: function () {
                  review.save();
                  }.bind(this),

                  }
              )
            }

            if (status === 'declined') {
            document.getElementById('ErrorMessages').innerHTML = 'Please try a different payment method.'
            }

            if (status === 'cancelled') {
            document.getElementById('ErrorMessages').innerHTML = 'Your finance application has not been submitted, please try again or choose a different payment method.'
            }

            }
        )
        checkoutHandler()

        }, () => {
        console.log('fail to load script')
        }
    )

  },

}

