<?php

/**
 *	This extension allows pages to have a fallback mapping for children that result in a page not found.
 *	@author Marcus Nyeholt <marcus@silverstripe.com.au>
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class MisdirectionFallbackExtension extends DataExtension {

	private static $db = array(
		'Fallback' => 'Varchar(255)',
		'FallbackURL' => 'Varchar(255)',
		'FallbackResponse' => 'Int'
	);

	private static $defaults = array(
		'FallbackResponse' => 303
	);

	/**
	 *	Display the appropriate fallback fields.
	 */

	public function updateCMSFields(FieldList $fields) {

		if($this->owner instanceof SiteConfig) {
			return $this->updateFields($fields);
		}
	}

	public function updateSettingsFields($fields) {

		// This extension only exists for site tree elements.

		return $this->updateFields($fields);
	}

	private function updateFields($fields) {

		Requirements::javascript(MISDIRECTION_PATH . '/javascript/misdirection-fallback.js');

		// Retrieve the fallback mapping selection.

		$tab = ($this->owner instanceof SiteConfig) ? 'Root.Pages' : 'Root.Misdirection';
		$fields->addFieldToTab($tab, HeaderField::create(
			'FallbackHeader',
			'Fallback'
		));
		$fields->addFieldToTab($tab, DropdownField::create(
			'Fallback',
			'To',
			array(
				'Nearest' => 'Nearest Parent',
				'This' => 'This Page',
				'URL' => 'URL'
			)
		)->addExtraClass('fallback')->setHasEmptyDefault(true)->setRightTitle('This will be used when children result in a <strong>page not found</strong>'));
		$fields->addFieldToTab($tab, TextField::create(
			'FallbackURL',
			'URL'
		)->addExtraClass('fallback-url'));

		// Retrieve the response code selection.

		$responses = Config::inst()->get('SS_HTTPResponse', 'status_codes');
		$selection = array();
		foreach($responses as $code => $description) {
			if(($code >= 300) && ($code < 400)) {
				$selection[$code] = "{$code}: {$description}";
			}
		}
		$fields->addFieldToTab($tab, DropdownField::create(
			'FallbackResponse',
			'Response Code',
			$selection
		)->addExtraClass('fallback-response'));
	}

}
