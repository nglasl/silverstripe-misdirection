<?php

/**
 *	Allow AJAX link mapping chain testing when viewing the LinkMappingAdmin.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class LinkMappingTest implements GridField_HTMLProvider {

	public function getHTMLFragments($gridfield) {

		Requirements::css(MISDIRECTION_PATH . '/css/link-mapping.css');
		Requirements::javascript(MISDIRECTION_PATH . '/javascript/link-mapping.js');
		return array(
			'before' => "<div class='link-mapping-test wrapper'>
				<div class='link-mapping-test admin'>
					<div><strong>Test Link Mappings</strong></div>
					<div><input class='url' spellcheck='false'/></div>
					<div class='results'></div>
					<span class='test disabled ss-ui-action-constructive ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary' data-icon='preview'>Test URL &raquo;</span>
				</div>
			</div>"
		);
	}

}
