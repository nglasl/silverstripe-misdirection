;(function($) {

	// Determine which fallback option display and functionality should be enabled.

	function toggleResponseCode() {

		var rule = $('select.fallback').val();
		if(rule) {
			(rule === 'URL') ? $('div.fallback-link').show() : $('div.fallback-link').hide();
			$('div.fallback-response-code').show();
		}
		else {
			$('div.fallback-link').hide();
			$('div.fallback-response-code').hide();
		}
	};

	// Bind the events dynamically.

	$('select.fallback').entwine({
		onmatch: function() {

			toggleResponseCode();
		},
		onchange: function() {

			toggleResponseCode();
		}
	});

})(jQuery);
