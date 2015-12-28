;(function($) {

	// Determine whether the validate external URL functionality should be enabled, as this does not expect a regular expression.

	function toggleValidateExternal() {

		($('select.link-type').val() === 'Simple') ? $('input.validate-external').parent().show() : $('input.validate-external').parent().hide();
	};

	// Bind the events dynamically.

	$('select.link-type').entwine({
		onmatch: function () {

			toggleValidateExternal();
		},
		onchange: function () {

			toggleValidateExternal();
		}
	});

})(jQuery);
