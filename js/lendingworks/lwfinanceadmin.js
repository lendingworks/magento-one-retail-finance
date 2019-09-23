var LendingWorksBackend = new Class.create()
LendingWorksBackend.prototype = {
  initialize: function (elem, methodCode) {
    this.code = methodCode;
  },


  fulfillmentButton: function (id) {
      currentStore = document.getElementById('lw-fulfillment-container').getAttribute('data-storeurl');
      new Ajax.Request(
          currentStore+'lwapi/order/fulfillorder', {
          method: 'POST',
          parameters: {
              lw_id: id,
          },
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
  }


}

