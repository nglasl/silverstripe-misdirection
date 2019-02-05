<?php

namespace nglasl\misdirection;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\SelectionGroup;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;
use Symbiote\Multisites\Multisites;

/**
 *	Simple and regular expression link redirection definitions.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class LinkMapping extends DataObject {

	private static $table_name = 'LinkMapping';

	/**
	 *	Manually define the redirect page relationship when the CMS module is not present.
	 */

	private static $db = array(
		'LinkType' => "Enum('Simple, Regular Expression', 'Simple')",
		'MappedLink' => 'Varchar(255)',
		'IncludesHostname' => 'Boolean',
		'Priority' => 'Int',
		'RedirectType' => "Enum('Link, Page', 'Link')",
		'RedirectLink' => 'Varchar(255)',
		'RedirectPageID' => 'Int',
		'ResponseCode' => 'Int',
		'HostnameRestriction' => 'Varchar(255)'
	);

	private static $defaults = array(
		'ResponseCode' => 301
	);

	/**
	 *	Make sure the link mappings are only ordered by priority and specificity when matching.
	 */

	private static $default_sort = 'ID DESC';

	private static $searchable_fields = array(
		'LinkType',
		'MappedLink',
		'Priority',
		'RedirectType'
	);

	private static $summary_fields = array(
		'MappedLink',
		'LinkSummary',
		'Priority',
		'RedirectTypeSummary',
		'RedirectPageTitle'
	);

	private static $field_labels = array(
		'MappedLink' => 'Mapping',
		'LinkSummary' => 'Redirection',
		'RedirectTypeSummary' => 'Redirect Type',
		'RedirectPageTitle' => 'Redirect Page Title'
	);

	/**
	 *	Make sure previous link mappings take precedence.
	 */

	private static $priority = 'ASC';

	/**
	 *	Keep track of the initial URL for regular expression pattern replacement.
	 *
	 *	@parameter <{URL}> string
	 */

	private $matchedURL;

	public function setMatchedURL($matchedURL) {

		$this->matchedURL = $matchedURL;
	}

	public function canView($member = null) {

		return true;
	}

	public function canEdit($member = null) {

		return Permission::checkMember($member, 'ADMIN');
	}

	public function canCreate($member = null, $context = array()) {

		return Permission::checkMember($member, 'ADMIN');
	}

	public function canDelete($member = null) {

		return Permission::checkMember($member, 'ADMIN');
	}

	/**
	 *	Print the mapped URL associated with this link mapping.
	 *
	 *	@return string
	 */

	public function getTitle() {

		return $this->MappedLink;
	}

	public function getCMSFields() {

		$fields = parent::getCMSFields();
		Requirements::css('nglasl/silverstripe-misdirection: client/css/misdirection.css');

		// Remove any fields that are not required in their default state.

		$fields->removeByName('MappedLink');
		$fields->removeByName('IncludesHostname');
		$fields->removeByName('Priority');
		$fields->removeByName('RedirectType');
		$fields->removeByName('RedirectLink');
		$fields->removeByName('RedirectPageID');
		$fields->removeByName('ResponseCode');
		$fields->removeByName('HostnameRestriction');

		// Update any fields that are displayed.

		$fields->dataFieldByName('LinkType')->addExtraClass('link-type')->setTitle('Type');

		// Instantiate the required fields.

		$fields->insertBefore(HeaderField::create(
			'MappedLinkHeader',
			'Mapping',
			3
		), 'LinkType');

		// Retrieve the mapped link configuration as a single grouping.

		$URL = FieldGroup::create(
			TextField::create(
				'MappedLink',
				''
			)->addExtraClass('mapped-link')->setDescription('This should <strong>not</strong> include the <strong>HTTP/S</strong> scheme'),
			CheckboxField::create(
				'IncludesHostname',
				'Includes Hostname?'
			)
		)->setTitle('URL');
		$fields->addFieldToTab('Root.Main', $URL);

		// Generate the 1 - 10 priority selection.

		$range = array();
		for($iteration = 1; $iteration <= 10; $iteration++) {
			$range[$iteration] = $iteration;
		}
		$fields->addFieldToTab('Root.Main', DropdownField::create(
			'Priority',
			null,
			$range
		));

		// Retrieve the redirection configuration as a single grouping.

		$fields->addFieldToTab('Root.Main', HeaderField::create(
			'RedirectionHeader',
			'Redirection',
			3
		));
		$redirect = FieldGroup::create();
		$redirect->push(TextField::create(
			'RedirectLink',
			''
		)->addExtraClass('redirect-link')->setDescription('This requires the <strong>HTTP/S</strong> scheme for an external URL'));

		// Allow redirect page configuration when the CMS module is present.

		if(ClassInfo::exists(SiteTree::class)) {

			// Allow redirect type configuration.

			if(!$this->RedirectType) {

				// Initialise the default redirect type.

				$this->RedirectType = 'Link';
			}
			$fields->addFieldToTab('Root.Main', SelectionGroup::create(
				'RedirectType',
				array(
					'Link//To URL' => $redirect,
					'Page//To Page' => TreeDropdownField::create(
						'RedirectPageID',
						'',
						SiteTree::class
					)
				)
			));
		}
		else {
			$redirect->setTitle('To URL');
			$fields->addFieldToTab('Root.Main', $redirect);
		}

		// Use third party validation against an external URL.

		if($this->canEdit()) {
			Requirements::javascript('nglasl/silverstripe-misdirection: client/javascript/misdirection-link-mapping.js');
			$redirect->push(CheckboxField::create(
				'ValidateExternal',
				'Validate External URL?'
			)->addExtraClass('validate-external'));
		}

		// Retrieve the response code selection.

		$responses = Config::inst()->get(MisdirectionRequestFilter::class, 'status_codes');
		$selection = array();
		foreach($responses as $code => $description) {
			if(($code >= 300) && ($code < 400)) {
				$selection[$code] = "{$code}: {$description}";
			}
		}
		$fields->addFieldToTab('Root.Main', DropdownField::create(
			'ResponseCode',
			'Response Code',
			$selection
		));

		// The optional hostname restriction is now deprecated.

		if($this->HostnameRestriction) {
			$fields->addFieldToTab('Root.Optional', TextField::create(
				'HostnameRestriction'
			));
		}

		// Allow extension customisation.

		$this->extend('updateLinkMappingCMSFields', $fields);
		return $fields;
	}

	public function validate() {

		$result = parent::validate();

		// Determine whether a regular expression mapping is possible to match against.

		if($result->isValid() && ($this->LinkType === 'Regular Expression') && (!$this->MappedLink || !is_numeric(@preg_match("%{$this->MappedLink}%", null)))) {
			$result->addError('Invalid regular expression!');
		}

		// Use third party validation to determine an external URL (https://gist.github.com/dperini/729294 and http://mathiasbynens.be/demo/url-regex).

		else if($result->isValid() && $this->ValidateExternal && $this->RedirectLink && !MisdirectionService::is_external_URL($this->RedirectLink)) {
			$result->addError('External URL validation failed!');
		}

		// Allow extension customisation.

		$this->extend('validateLinkMapping', $result);
		return $result;
	}

	/**
	 *	Unify any URLs that may have been defined.
	 */

	public function onBeforeWrite() {

		parent::onBeforeWrite();
		$this->MappedLink = MisdirectionService::unify_URL($this->MappedLink);
		$this->RedirectLink = trim($this->RedirectLink, ' ?/');
		$this->HostnameRestriction = MisdirectionService::unify_URL($this->HostnameRestriction);
	}

	/**
	 *	Retrieve the page associated with this link mapping redirection.
	 *
	 *	@return site tree
	 */

	public function getRedirectPage() {

		return (ClassInfo::exists(SiteTree::class) && $this->RedirectPageID) ? SiteTree::get()->byID($this->RedirectPageID) : null;
	}

	/**
	 *	Retrieve the redirection URL.
	 *
	 *	@return string
	 */

	public function getLink() {

		if($this->RedirectType === 'Page') {

			// Determine the home page URL when appropriate.

			if(($page = $this->getRedirectPage()) && ($link = ($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link())) {

				// This is to support multiple sites, where the absolute page URLs are treated as relative.

				return MisdirectionService::is_external_URL($link) ? ltrim($link, '/') : $link;
			}
		}
		else {

			// Apply the regular expression pattern replacement.

			if($link = (($this->LinkType === 'Regular Expression') && $this->matchedURL) ? preg_replace("%{$this->MappedLink}%i", $this->RedirectLink, $this->matchedURL) : $this->RedirectLink) {

				// When appropriate, prepend the base URL to match a page redirection.

				$prepended = Controller::join_links(Director::baseURL(), $link);
				if(MisdirectionService::is_external_URL($link)) {
					return ClassInfo::exists(Multisites::class) ? HTTP::setGetVar('misdirected', true, $link) : $link;
				}

				// This is needed, otherwise infinitely recursive mappings won't be detected in advance.

				else if(MisdirectionService::is_external_URL($prepended)) {
					return $link;
				}
				else {
					return $prepended;
				}
			}
		}

		// No redirection URL has been found.

		return null;
	}

	/**
	 *	Retrieve the redirection hostname.
	 *
	 *	@return string
	 */

	public function getLinkHost() {

		if($this->RedirectType === 'Page') {

			// Determine the home page URL when appropriate.

			if(($page = $this->getRedirectPage()) && ($link = ($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link())) {

				// Determine whether a redirection hostname exists.

				return MisdirectionService::is_external_URL($link) ? parse_url($link, PHP_URL_HOST) : null;
			}
		}
		else {

			// Apply the regular expression pattern replacement.

			if($link = (($this->LinkType === 'Regular Expression') && $this->matchedURL) ? preg_replace("%{$this->MappedLink}%i", $this->RedirectLink, $this->matchedURL) : $this->RedirectLink) {

				// Determine whether a redirection hostname exists.

				return MisdirectionService::is_external_URL($link) ? parse_url($link, PHP_URL_HOST) : null;
			}
		}

		// No redirection hostname has been found.

		return null;
	}

	/**
	 *	Retrieve the redirection URL for display purposes.
	 *
	 *	@return string
	 */

	public function getLinkSummary() {

		return ($link = $this->getLink()) ? trim($link, ' ?/') : '-';
	}

	/**
	 *	Retrieve the redirection type for display purposes.
	 *
	 *	@return string
	 */

	public function getRedirectTypeSummary() {

		return $this->RedirectType ? $this->RedirectType : '-';
	}

	/**
	 *	Retrieve the page title associated with this link mapping redirection.
	 *
	 *	@return string
	 */

	public function getRedirectPageTitle() {

		return (($this->RedirectType === 'Page') && ($page = $this->getRedirectPage())) ? $page->Title : '-';
	}

	/**
	 *	Determine if the link mapping is live on the current stage.
	 *
	 *	@return string
	 */

	public function isLive() {

		return ($this->RedirectType === 'Page') ? ($this->getRedirectPage() ? 'true' : 'false') : '-';
	}

}
