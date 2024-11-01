


jQuery(document).ready(function(){
    jQuery(".btn-test-api").bind("click", function() {
        var button = jQuery(this);
        var btnNonce=button.data("nonce");
        var tbWidgetId = button.attr("id");
        var spinner = jQuery("#api-test-spinner-"+tbWidgetId);
        var resultContainer = jQuery("#api-test-result-"+tbWidgetId);
        
        spinner.show();
        button.hide();
        resultContainer.empty();
        
        jQuery.ajax({
          type:'POST',
          data:{action:'tb_test_api','nonce':btnNonce, id:tbWidgetId},
          url: ajaxurl,
          success: function(response) {
            button.show();
            spinner.hide();
            resultContainer.html(response);
          }
        });
    });
});