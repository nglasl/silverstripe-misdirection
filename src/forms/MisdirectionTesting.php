<?php

namespace nglasl\misdirection;

use SilverStripe\Forms\GridField\GridField_HTMLProvider;

/**
 *	The testing interface used to view the link mapping recursion stack.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class MisdirectionTesting implements GridField_HTMLProvider {

	/**
	 *	Render the URL input and test button.
	 */

	public function getHTMLFragments($gridfield) {

		return array(
			'before' => "<div class='misdirection-testing admin'>
				<div><strong>Test Link Mappings</strong></div>
				<div class='wrapper'>
					<input type='text' class='text w-50 url' spellcheck='false'/>
					<span role='button' class='btn btn-notice font-icon-switch test disabled' tabindex='0'>
						<span class='btn__title'>Test URL</span>
					</span>
				</div>
				<div class='results'></div>
			</div>"
		);
	}

}
