<?php

/**
 *	Misdirection testing interface used to view the link mapping recursion stack.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class MisdirectionTesting implements GridField_HTMLProvider {

	/**
	 *	Render the URL input and test button.
	 */

	public function getHTMLFragments($gridfield) {

		return array(
			'before' => "<div class='misdirection-testing wrapper'>
				<div class='misdirection-testing admin'>
					<div><strong>Test Link Mappings</strong></div>
					<div><input class='url' spellcheck='false'/></div>
					<div class='results'></div>
					<span class='test disabled ss-ui-action-constructive ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary' data-icon='preview'>Test URL &raquo;</span>
				</div>
			</div>"
		);
	}

}
