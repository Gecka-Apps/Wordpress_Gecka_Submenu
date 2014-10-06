(function($) {

    gksm_widget_update_select = function (elem, target) {

	    var data = { action: 'gksm_update',
				     ID: elem.value,
				     _ajax_nonce: gksm_widget.custom_menu_nonce
				    };
				
	    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post(ajaxurl, data, function(response) {
		    $(target).html(response);
		});
	};


})(jQuery);
