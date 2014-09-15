;(function($) {
	$(function() {

		// Determine which fallback option display and functionality should be enabled.

		function toggle() {

			var rule = $('select[name=FallbackRule]').val();
			if(rule) {
				$('#FallbackResponse').show();
				(rule === 'URL') ? $('#FallbackUrl').show() : $('#FallbackUrl').hide();
			}
			else {
				$('#FallbackResponse').hide();
				$('#FallbackUrl').hide();
			}
		};

		// Bind the events dynamically.

		$('select[name=FallbackRule]').entwine({
			onmatch: function () {

				toggle();
			},
			onchange: function () {

				toggle();
			}
		});

	});
})(jQuery);
