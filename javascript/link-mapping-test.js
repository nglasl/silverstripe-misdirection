;(function($) {

	// Determine whether the test button display and functionality should be enabled.

	function enable(input) {

		var URL = input ? input.val() : $('div.link-mapping-test.admin input.url').val();
		var button = $('div.link-mapping-test.admin span.test');
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
		$('#Form_EditForm').removeClass('changed');
	};

	// Test the link mapping chain for a given URL.

	function test(input) {

		var URL = input ? input.val() : $('div.link-mapping-test.admin input.url').val();
		$.getJSON('admin/misdirection/LinkMapping/getMappingChain', {
			map: URL
		},
		function(JSON) {

			var output = '';
			if(JSON) {

				// Construct the link mapping chain HTML representation.

				$.each(JSON, function(index, object) {

					output += "<div class='result'>";
					if(object['ResponseCode'] !== 404) {
						output += '<h3><strong>' + object['Counter'] + '</strong></h3>';
						output += '<div><strong>Link Type</strong> ' + object['LinkType'] + '</div>';
						output += '<div><strong>Mapped Link</strong> ' + object['MappedLink'] + '</div>';
						output += '<div><strong>Redirect Link</strong> ' + object['RedirectLink'] + '</div>';
						output += '<div><strong>Response Code</strong> ' + object['ResponseCode'] + '</div>';
						output += '<div><strong>Priority</strong> ' + object['Priority'] + '</div>';
					}
					else {
						output += '<h3><strong>Maximum</strong></h3>';
						output += '<div><strong>Response Code</strong> ' + object['ResponseCode'] + '</div>';
					}
					output += '</div>';
				});
			}
			else {
				output += "<div class='result'>";
				output += '<h3><strong>No Matches</strong></h3>';
				output += '</div>';
			}

			// Render the link mapping chain.

			$('div.link-mapping-test.admin div.results').html(output);
		});
	};

	// Bind the events dynamically.

	$.entwine('ss', function($) {

		// Trigger an interface update on key press.

		$('div.link-mapping-test.admin input.url').entwine({
			onchange: function() {

				enable($(this));
			},
			onkeydown: function(event) {

				var input = $(this);

				// Trigger a test request on pressing enter.

				if(event.keyCode === 13) {
					if(input.val().length > 0) {
						test();

						// Trigger an interface update.

						input.change();
					}
					return false;
				}
			}
		});

		// Trigger an interface update and handle any test request.

		$('div.link-mapping-test.admin span.test').entwine({
			onmouseenter: function() {

				enable();
			},
			onclick: function() {

				if(!$(this).hasClass('disabled')) {

					// Test the link mapping chain for the given URL, and render this to the user.

					test();
				}
			}
		});
	});

})(jQuery);
