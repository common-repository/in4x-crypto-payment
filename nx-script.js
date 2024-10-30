buttonClick = function () {
  jQuery(".nx-error-wrapper").hide();
  var apiKey = jQuery(this).data('api-key');
  var overrideUrl = jQuery(this).data('override-url');
  var params = new URLSearchParams(window.location.search);
  if(window.in4xGlobalWidget && apiKey && params && params.has('pid')) {
    var widgetParams = {
                  apiKey: apiKey,
                  paymentIdReference: params.get('pid'),
                  hostOverride: overrideUrl
                };
    console.warn("[NXWidget] Opening Payment", widgetParams);
    window.in4xGlobalWidget.showPaymentIframe(widgetParams);
    jQuery(this).text('Retry Payment');
  } else {
    console.error('Invalid pay button', apiKey, params.get('pid'), window.in4xGlobalWidget);
  }
}

onWidgetClose = function(event) {
  if(event && event.detail) {
    if(event.detail.status.toLocaleLowerCase() === 'complete' || 
       event.detail.status.toLocaleLowerCase() === 'success' ||
       event.detail.status.toLocaleLowerCase() === 'processed'
    ) {
        jQuery(".nx-message").show();
        jQuery(".nx-message").text("Thank you, your payment was processed successfully");
        jQuery(".nx-message").addClass("woocommerce-message");
        jQuery(".nx-error-wrapper").hide();
        jQuery(".nx-pay-button").hide();
    } else {
        jQuery(".nx-message").hide();
        jQuery(".nx-error-wrapper").show();
        jQuery(".nx-error").text("Your payment is not complete, please try again");
    }
    jQuery('#in4xGlobalWidget_container').remove();
  }
}

jQuery(document).ready(function () {
  jQuery("#woocommerce_nx_listen_url").attr("readonly", "readonly");
  var payButton = document.querySelector(".nx-pay-button");
  if(payButton) {
    payButton.addEventListener("click", buttonClick);
    window.addEventListener("in4xEvent", onWidgetClose);
  }
});