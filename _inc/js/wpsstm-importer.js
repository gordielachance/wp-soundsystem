var $ = jQuery.noConflict();

$( document ).ready(function() {

    var $feedUrlHelpContent = $("#wpsstm-importers");
    var $feedUrlHelpHandle = $("#feed-url-help");
    $feedUrlHelpContent.hide();

    $feedUrlHelpHandle.click(function(e) {
      e.preventDefault();
      $feedUrlHelpContent.toggle();
    });

    /*
    schema nodes
    */

    $('a.wpsstm-wizard-node-handle').click(function(e) {
        e.preventDefault();
        var node = $(this).closest('.wpsstm-wizard-node');
        node.toggleClass('wpsstm-wizard-node-active');
    });

});
