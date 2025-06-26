jQuery(document).ready( function($) {

	// From http://www.johngadbois.com/adding-your-own-callbacks-to-wordpress-ajax-requests/
	jQuery('body').ajaxSuccess(function(evt, request, settings) {
		jQuery('a.live-stream-terms-show').click(live_stream_terms_show);
		jQuery('ul.live-stream-admin-checklist input').click(live_stream_checklist_click);
		jQuery('input.live-stream-terms-select-all').click(live_Stream_terms_select_all_click);	
	
	});
	
	jQuery('a.live-stream-terms-show').click(live_stream_terms_show);
	jQuery('ul.live-stream-admin-checklist input').click(live_stream_checklist_click);
	jQuery('input.live-stream-terms-select-all').click(live_Stream_terms_select_all_click);	
	
	function live_stream_terms_show(event) {

		var parent = jQuery(this).parent();
		var select_all = jQuery('input.live-stream-terms-select-all', parent);
		if (jQuery(select_all).is(':checked')) {
			//console.log('select all is CHECKED');
			jQuery(select_all).removeAttr('checked');
		} 
		var wrapper = jQuery(this).parent().next('div.terms-wrapper');
		if (!jQuery(wrapper).is(":visible"))
			jQuery(wrapper).show();
		
		var wrapper_ul = jQuery('ul', wrapper);
		if (jQuery(wrapper_ul).is(":visible"))
		{
			jQuery(wrapper_ul).slideUp();
		} else {		
			jQuery(wrapper_ul).slideDown();		
		}

		event.stopImmediatePropagation();
		return false;
	}
	
	function live_stream_checklist_click(event) {

		var parent_div = jQuery(this).parents('div.terms-wrapper');
		var parent_ul = $(this).parents('ul');

		selected_terms_array = [];

		jQuery(parent_ul).find('input:checked').each( function() {
			var parent_item = $(this).parent();
			var label_text = $(parent_item).find('span.label-text').html();		
			selected_terms_array[selected_terms_array.length] = label_text;			
		});

		if (selected_terms_array.length) {
			selected_terms_array.sort();
			selected_terms_html = selected_terms_array.join(', ');
			jQuery(parent_div).find('p span.selected-terms').html(selected_terms_html);
		} else {
			jQuery(parent_div).find('p span.selected-terms').html('No terms selected');
		}
		event.stopImmediatePropagation();
		return;
	}
	
	function live_Stream_terms_select_all_click(event) {
		var wrapper = jQuery(this).parent().next('div.terms-wrapper');
		if (jQuery(this).is(':checked')) {
			jQuery(wrapper).hide();
		} else {
			jQuery(wrapper).slideDown('slow');
		}
		event.stopImmediatePropagation();
		return;
	}
});
