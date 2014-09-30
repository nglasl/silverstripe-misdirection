<?php

/**
 *	Allow AJAX link mapping chain testing when viewing the LinkMappingAdmin.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class MisdirectionAdminTestExtension extends Extension {

	public function updateEditForm(&$form) {

		// Restrict the testing interface to administrators.

		Requirements::css(MISDIRECTION_PATH . '/css/link-mapping.css');
		$user = Member::currentUserID();
		if(Permission::checkMember($user, 'ADMIN')) {
			$gridfield = $form->fields->items[0];
			if(isset($gridfield)) {
				$configuration = $gridfield->config;

				// Add the required HTML fragment.

				Requirements::javascript(MISDIRECTION_PATH . '/javascript/link-mapping-test.js');
				$configuration->addComponent(new LinkMappingTest());
			}
		}
	}

}
