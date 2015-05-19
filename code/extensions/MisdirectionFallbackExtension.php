<?php

/**
 * Extension that allows pages + site config to have "fallback" mapping rules
 * specified for them. 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class MisdirectionFallbackExtension extends DataExtension {

	// Allow setting fallback rules on a per page basis.

	private static $db = array(
		'FallbackRule'		=> 'Varchar',
		'FallbackUrl'		=> 'Varchar(255)',
		'FallbackResponse'	=> 'Varchar',
	);
	
	protected function updateFields(FieldList $fields) {

		Requirements::javascript(MISDIRECTION_PATH . '/javascript/misdirection-fallback.js');
		
		// Allow customisation of fallback rules.
		$fields->addFieldToTab('Root.Misdirection', HeaderField::create(
			'FallbackHeader',
			_t('LinkMapping.FallbackHeader', 'Fallback')
		));
		$options = array(
			'URL'		=> _t('LinkMapping.STRAIGHT_URL', 'Specific URL'),
			'ThisPage'	=> _t('LinkMapping.THIS_PAGE', 'This Page'),
			'Nearest'	=> _t('LinkMapping.NEAREST', 'Nearest Parent')
		);
		
		// Retrieve the response code listing.
		$responseCodes = Config::inst()->get('SS_HTTPResponse', 'status_codes');
		$redirectCodes = array();
		foreach($responseCodes as $code => $description) {
			if ($code >= 300 && $code < 400) {
				$redirectCodes[$code] = "{$code}: $description";
			}
		}

		$info = _t('LinkMapping.FALLBACK_DETAILS', 'Select a method to use for handling any missing child page');
		$field = DropdownField::create(
				'FallbackRule', 
				_t('LinkMapping.FALLBACK_RULE', 'Rule'),
				$options
			)->setRightTitle($info)->setHasEmptyDefault(true)->addExtraClass('fallback-rule');
		
		$fields->addFieldToTab('Root.Misdirection', $field);
		$fields->addFieldToTab('Root.Misdirection', TextField::create('FallbackUrl', _t('LinkMapping.FALLBACK_URL', 'To URL'))->addExtraClass('fallback-to'));
		$fields->addFieldToTab('Root.Misdirection', DropdownField::create(
				'FallbackResponse', 
				_t('LinkMapping.FALLBACK_RESPONSE', 'Response Code'),
				$redirectCodes
			)->setHasEmptyDefault(true)->addExtraClass('fallback-response')
		);
	}

	public function updateCMSFields(FieldList $fields) {
		if ($this->owner instanceof SiteConfig) {
			return $this->updateFields($fields);
		}
	}

	public function updateSettingsFields(FieldList $fields) {
		return $this->updateFields($fields);
	}
}
