<?php

/**
 *	Allow AJAX link mapping chain testing when viewing the LinkMappingAdmin.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class LinkMappingAdminTestExtension extends Extension {

	public function updateEditForm(&$form) {

		$gridfield = $form->fields->items[0];
		if(isset($gridfield)) {
			$configuration = $gridfield->config;

			// Add the required HTML fragment.

			$configuration->addComponent(new LinkMappingTest());
		}
	}

}
