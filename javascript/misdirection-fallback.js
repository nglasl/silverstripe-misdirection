;(function($) {

	// Determine which fallback option display and functionality should be enabled.

	function toggle() {

		var rule = $('select.fallback').val();
		if(rule) {
			(rule === 'URL') ? $('div.fallback-url').show() : $('div.fallback-url').hide();
			$('div.fallback-response').show();
		}
		else {
			$('div.fallback-url').hide();
			$('div.fallback-response').hide();
		}
	};

	// Bind the events dynamically.

	$('select.fallback').entwine({
		onmatch: function () {

			toggle();
		},
		onchange: function () {

			toggle();
		}
	});

})(jQuery);
