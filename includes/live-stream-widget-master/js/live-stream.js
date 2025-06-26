(function($){
"use strict";
	
	var update_selector = null;
	var controls_selector = null;
	var containerHeight = null;
	var widget_id = null;
	
	var visible_top_item_id = '';
	var visible_bottom_item_id = '';

    $.LiveStreamUpdates = function(selector, settings) {
		// settings
		var config = {
			'delay': 2000,
			'widget_id': 0,
			'max_items': 0
        };

        if ( settings ){$.extend(config, settings);}
		
		if (selector != '') {
			update_selector 	= selector+' .live-stream-items-wrapper';
				
	        setInterval(function() {
				// We need to find the ID of the first/latest item displayed. Then pass this to AJAX so we can pull more recent items
				var latest_id = jQuery(update_selector+' .live-stream-item').first().attr('id');
				
				if (latest_id) {
					var latest_id_parts = latest_id.split('-');
					if (latest_id_parts[3] != "") {
						getUpdates(config.widget_id, latest_id_parts[3], update_selector, config.max_items);
					}				
				}

	        }, config.delay);
		}
		 
        return this;
    }

	function getUpdates(widget_id, timekey, update_selector, max_items) {

		var data = {
			action: 'live_stream_update_ajax',
			widget_id: widget_id,
			timekey: timekey
		};
			
		jQuery.ajax({
		  	type: 'POST',
		  	url: live_stream_data.ajaxurl,
	        data: data,
			cache: false,
			dataType: 'html',
			success: function( reply_data ) {
				if (reply_data != "") {	
					
					jQuery(reply_data).hide().prependTo(update_selector).slideDown("slow");
					jQuery(update_selector+' li:gt('+max_items+')').hide();
				}				
			}
		});		
	}

})(jQuery);
