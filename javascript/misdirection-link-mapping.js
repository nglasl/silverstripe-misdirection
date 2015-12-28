;(function($) {

	// Determine whether to display the validate external URL.

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
