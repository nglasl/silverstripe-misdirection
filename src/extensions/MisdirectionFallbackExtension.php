<?php

namespace nglasl\misdirection;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

/**
 *	This extension allows pages to have a fallback mapping for children that result in a page not found.
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

		Requirements::javascript('nglasl/silverstripe-misdirection: client/javascript/misdirection-fallback.js');

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
		)->addExtraClass('fallback')->setHasEmptyDefault(true)->setDescription('This will be used when children result in a <strong>page not found</strong>'));
		$fields->addFieldToTab($tab, TextField::create(
			'FallbackLink',
			'URL'
		)->addExtraClass('fallback-link')->setDescription('This requires the <strong>HTTP/S</strong> scheme for an external URL'));

		// Retrieve the response code selection.

		$responses = Config::inst()->get(MisdirectionRequestFilter::class, 'status_codes');
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
