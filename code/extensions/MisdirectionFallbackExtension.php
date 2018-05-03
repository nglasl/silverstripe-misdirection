<?php

/**
 *	This extension allows pages to have a fallback mapping for children that result in a page not found.
 *	@author Marcus Nyeholt <marcus@symbiote.com.au>
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class MisdirectionFallbackExtension extends DataExtension {

	private static $db = array(
		'Fallback' => 'Varchar(255)',
		'FallbackLink' => 'Varchar(255)',
		'FallbackResponseCode' => 'Int'
	);

	private static $defaults = array(
		'FallbackResponseCode' => 303
	);

	/**
	 *	Display the appropriate fallback fields.
	 */

	public function updateCMSFields(FieldList $fields) {

		if($this->owner instanceof SiteConfig) {
			return $this->owner->updateFields($fields);
		}
	}

	public function updateSettingsFields($fields) {

		// This extension only exists for pages.

		return $this->owner->updateFields($fields);
	}

	public function updateFields($fields) {

		Requirements::javascript(MISDIRECTION_PATH . '/javascript/misdirection-fallback.js');

		// Update any fields that are displayed when not viewing a page.

		$tab = 'Root.Misdirection';
		$options = array(
			'Nearest' => 'Nearest Parent',
			'This' => 'This Page',
			'URL' => 'URL'
		);
		if($this->owner instanceof SiteConfig) {
			$tab = 'Root.Pages';
			unset($options['This']);
		}

		// Retrieve the fallback mapping selection.

		$fields->addFieldToTab($tab, HeaderField::create(
			'FallbackHeader',
			'Fallback'
		));
		$fields->addFieldToTab($tab, DropdownField::create(
			'Fallback',
			'To',
			$options
		)->addExtraClass('fallback')->setHasEmptyDefault(true)->setRightTitle('This will be used when children result in a <strong>page not found</strong>'));
		$fields->addFieldToTab($tab, TextField::create(
			'FallbackLink',
			'URL'
		)->addExtraClass('fallback-link')->setRightTitle('This requires the <strong>HTTP/S</strong> scheme for an external URL'));

		// Retrieve the response code selection.

		$responses = Config::inst()->get('SS_HTTPResponse', 'status_codes');
		$selection = array();
		foreach($responses as $code => $description) {
			if(($code >= 300) && ($code < 400)) {
				$selection[$code] = "{$code}: {$description}";
			}
		}
		if(!$this->owner->FallbackResponseCode) {
			$this->owner->FallbackResponseCode = 303;
		}
		$fields->addFieldToTab($tab, DropdownField::create(
			'FallbackResponseCode',
			'Response Code',
			$selection
		)->addExtraClass('fallback-response-code'));

		// Allow extension customisation.

		$this->owner->extend('updateMisdirectionFallbackExtensionFields', $fields);
	}

}
