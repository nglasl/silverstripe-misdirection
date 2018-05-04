;(function($) {

	var page = $(document);

	// Determine whether the test button display and functionality should be enabled.

	function enable(input) {

		var URL = input ? input.val() : $('div.misdirection-testing.admin input.url').val();
		var button = $('div.misdirection-testing.admin span.test');
		if(URL.length > 0) {
			button.fadeTo(250, 1, function() {

				button.removeClass('disabled');
			});
		}
		else {
			button.fadeTo(250, 0.4, function() {

				button.addClass('disabled');
			});
		}
	};

	// Test the link mapping chain for a given URL.

	function test(input) {

		// Trigger an interface update to reflect the pending request.

		var results = $('div.misdirection-testing.admin div.results');
		results.html('');
		results.addClass('loading');

		// Test the link mapping chain.

		var URL = input ? input.val() : $('div.misdirection-testing.admin input.url').val();
		$.getJSON('admin/misdirection/nglasl-misdirection-LinkMapping/getMappingChain', {
			map: URL
		},
		function(JSON) {

			var output = '';
			if(JSON) {

				// Construct the link mapping chain HTML representation.

				$.each(JSON, function(index, object) {

					output += "<div class='result'>";
					if(object['ResponseCode'] !== 404) {
						output += "<div class='h3'><strong>" + object['Counter'] + '</strong></div>';
						output += '<div><strong>Link Type</strong> ' + object['LinkType'] + '</div>';
						output += '<div><strong>Mapped Link</strong> ' + object['MappedLink'].replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
						output += '<div><strong>Redirect Link</strong> ' + object['RedirectLink'].replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
						output += '<div><strong>Response Code</strong> ' + object['ResponseCode'] + '</div>';
						output += '<div><strong>Priority</strong> ' + object['Priority'] + '</div>';
					}
					else {
						output += "<div class='h3'><strong>Maximum</strong></div>";
						output += '<div><strong>Response Code</strong> ' + object['ResponseCode'] + '</div>';
					}
					output += '</div>';
				});
			}
			else {
				output += "<div class='result no-matches'>";
				output += "<div class='h3'><strong>No Matches</strong></div>";
				output += '</div>';
			}

			// Render the link mapping chain.

			results.removeClass('loading');
			results.html(output);
		});
	};

	// Trigger an interface update on input.

	page.on('input', 'div.misdirection-testing.admin input.url', function() {

		enable($(this));
	});

	page.on('blur', 'div.misdirection-testing.admin input.url', function() {

		// Make sure the edit form doesn't detect changes.

		$('form#Form_EditForm').removeClass('changed');
	});

	// The testing.

	page.on('keydown', 'div.misdirection-testing.admin input.url, div.misdirection-testing.admin span.test', function(event) {

		// Trigger a test request on pressing enter.

		if(event.keyCode === 13) {
			var input = $('div.misdirection-testing.admin input.url');
			if(input.val().length > 0) {

				// Test the link mapping chain for the given URL, and render this to the user.

				test(input);
			}
			return false;
		}
	});

	page.on('click', 'div.misdirection-testing.admin span.test', function() {

		if(!$(this).hasClass('disabled')) {

			// Test the link mapping chain for the given URL, and render this to the user.

			test();
		}
	});

})(jQuery);
