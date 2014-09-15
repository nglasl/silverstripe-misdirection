
;(function($) {
	$(function () {
		var checkFallbackType = function () {
			var currentVal = $('select[name=FallbackRule]').val();
			
			if (currentVal) {
				$('#FallbackResponse').show();
				
				if (currentVal === 'URL') {
					$('#FallbackUrl').show();
				} else {
					$('#FallbackUrl').hide();
				}
			} else {
				$('#FallbackResponse').hide();
				$('#FallbackUrl').hide();
			}
		}
		// bind toggle behaviour for fallback type
		$('select[name=FallbackRule]').entwine({
			onmatch: function () {
				checkFallbackType();
			},
			onchange: function () {
				checkFallbackType();
			}
		});
		
		
	})
})(jQuery);