;(function($) {
	$(window).load(function() {

		// Determine which fallback option display and functionality should be enabled.

		function toggle() {

			var rule = $('select.fallback-rule').val();
			if(rule) {
				$('div.fallback-response').show();
				(rule === 'URL') ? $('div.fallback-to').show() : $('div.fallback-to').hide();
			}
			else {
				$('div.fallback-response').hide();
				$('div.fallback-to').hide();
			}
		};

		// Bind the events dynamically.

		$('select.fallback-rule').entwine({
			onmatch: function () {

				toggle();
			},
			onchange: function () {

				toggle();
			}
		});

	});
})(jQuery);
