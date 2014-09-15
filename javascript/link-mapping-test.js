/*
	This functionality has been duplicated using the initial implementation from https://github.com/nglasl/silverstripe-apiwesome
	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

;(function($) {
	$(function() {

		// Determine whether the test URL button display and functionality should be enabled.

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
		};

		// Retrieve the link mapping chain for a given URL.

		function retrieveLinkMappingChain(map) {

			$.getJSON('admin/misdirection/LinkMapping/getLinkMappingChain',
				{
					map: map
				},
				function(JSON) {

					var output = '';
					if(JSON) {

						// Iterate over each link mapping and collate any important data.

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

					// Display these to the user.

					$('div.link-mapping-test.admin div.results').html(output);
				});
		}

		// Bind the mouse events dynamically.

		$.entwine('ss', function($) {

			// Trigger an interface update on key press.

			$('div.link-mapping-test.admin input.url').entwine({
				onchange: function() {

					enable($(this));
				}
			});

			// Trigger an interface update and handle any preview request.

			$('div.link-mapping-test.admin span.test').entwine({
				onmouseenter: function() {

					enable();
				},
				onclick: function() {

					if(!$(this).hasClass('disabled')) {

						// Retrieve the link mapping chain using the current test URL and display this to the user.

						retrieveLinkMappingChain($('div.link-mapping-test.admin input.url').val());
					}
				}
			});
		});

	});
})(jQuery);
