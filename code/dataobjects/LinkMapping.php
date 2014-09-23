<?php
/**
 * A link mapping that connects a link to either a redirected link or another
 * page on the site.
 *
 * @package silverstripe-linkmapping
 */
class LinkMapping extends DataObject {

	// Define the redirect page through DB fields if the CMS module is not present.

	private static $db = array(
		'LinkType' => "Enum('Simple, Regular Expression', 'Simple')",
		'MappedLink'   => 'Varchar(255)',
		'RedirectType' => "Enum('Page, Link', 'Link')",
		'RedirectLink' => 'Varchar(255)',
		'RedirectPageID' => 'Int',
		'ResponseCode' => 'Int',
		'ForwardPOSTRequest' => 'Boolean',
		'Priority' => 'Int',
		'HostnameRestriction' => 'Varchar(255)'
	);

	private static $defaults = array(
		'ResponseCode' => 303
	);

	private static $summary_fields = array(
		'MappedLink',
		'RedirectType',
		'Stage',
		'RedirectPageLink',
		'RedirectPageTitle',
		'Host'
	);

	private static $field_labels = array(
		'RedirectPageLink' => 'Redirect Link',
		'RedirectPageTitle' => 'Redirect Page Title',
		'Host' => 'Hostname Restriction'
	);

	private static $searchable_fields = array(
		'MappedLink'   => array('filter' => 'PartialMatchFilter'),
		'RedirectType' => array('filter' => 'ExactMatchFilter')
	);

	// Make sure link mappings are displayed in order of creation, but priority based when matching.

	private static $default_sort = array(
		'ID' => 'DESC'
	);

	private static $priority = 'DESC';

	// Use the initial request URL for a redirect link regular expression replacement.

	private $matchedURL;

	public function setMatchedURL($matchedURL) {

		$this->matchedURL = $matchedURL;
	}

	public function getCMSFields() {

		$fields = parent::getCMSFields();
		Requirements::css(MISDIRECTION_PATH . '/css/link-mapping.css');

		// Remove any fields that are either not required or need to be moved around.

		$fields->removeByName('RedirectType');
		$fields->removeByName('RedirectLink');
		$fields->removeByName('RedirectPageID');
		$fields->removeByName('ResponseCode');
		$fields->removeByName('ForwardPOSTRequest');
		$fields->removeByName('Priority');
		$fields->removeByName('HostnameRestriction');

		$fields->insertBefore(HeaderField::create(
			'MappedLinkHeader', $this->fieldLabel('MappedLinkHeader')
		), 'LinkType');

		// Generate the link mapping priority selection from 1 - 10.

		$range = array();
		for($i = 1; $i <= 10; $i++) {
			$range[$i] = $i;
		}
		$fields->addFieldToTab('Root.Main', DropdownField::create('Priority', _t('LinkMapping.PRIORITY', 'Priority'), $range));
		$fields->addFieldToTab('Root.Main', HeaderField::create(
			'RedirectToHeader', $this->fieldLabel('RedirectToHeader')
		));

		// Collate the redirect settings into a single grouping.

		$redirect = FieldGroup::create()->addExtraClass('redirect-link');
		if(ClassInfo::exists('SiteTree')) {
			$pageLabel = $this->fieldLabel('RedirectToPage');
			$linkLabel = $this->fieldLabel('RedirectToLink');
			$fields->addFieldToTab('Root.Main', SelectionGroup::create('RedirectType', array(
				"Page//$pageLabel" => TreeDropdownField::create('RedirectPageID', '', 'Page'),
				"Link//$linkLabel" => $redirect
			))->addExtraClass('field redirect'));
			$redirect->push($redirectLink = TextField::create('RedirectLink', ''));
		}
		else {
			$redirect->setTitle(_t('LinkMapping.REDIRECTLINK', 'Redirect Link'));
			$fields->addFieldToTab('Root.Main', $redirect);
			$redirect->push($redirectLink = TextField::create('RedirectLink', ''));
		}
		$redirect->push(CheckboxField::create('ValidateExternalURL'));
		$redirectLink->setRightTitle('External URLs will require the protocol explicitly defined');

		// Retrieve the response code listing.

		$responseCodes = Config::inst()->get('SS_HTTPResponse', 'status_codes');
		$redirectCodes = array();
		foreach($responseCodes as $code => $description) {
			if ($code >= 300 && $code < 400) {
				$redirectCodes[$code] = "{$code}: $description";
			}
		}

		// Collate the response settings into a single grouping.

		$response = FieldGroup::create(
			DropdownField::create('ResponseCode', '', $redirectCodes),
			CheckboxField::create('ForwardPOSTRequest', _t('LinkMapping.FORWARDPOSTREQUEST', 'Forward POST Request'))
		)->setTitle(_t('LinkMapping.RESPONSECODE', 'Response Code'))->addExtraClass('response');
		$fields->addFieldToTab('Root.Main', $response);

		// Create an optional tab to manage the hostname restriction.

		$fields->addFieldToTab('Root.Optional', TextField::create('HostnameRestriction'));

		return $fields;
	}

	public function onBeforeWrite() {

		parent::onBeforeWrite();
		$this->MappedLink = MisdirectionService::unify($this->MappedLink);
		$this->RedirectLink = MisdirectionService::unify($this->RedirectLink);
	}

	public function validate() {

		$result = parent::validate();
		if($this->ValidateExternalURL && $this->RedirectLink) {

			// The following validation translation comes from: https://gist.github.com/dperini/729294 and http://mathiasbynens.be/demo/url-regex

			$this->RedirectLink = trim($this->RedirectLink, '!"#$%&\'()*+,-./@:;<=>[\\]^_`{|}~');
			preg_match('%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu', $this->RedirectLink) ?
				$result->valid() :
				$result->error('External URL validation failed!');
		}
		return $result;
	}

	public function fieldLabels($includerelations = true) {
		return parent::fieldLabels($includerelations) + array(
			'MappedLinkHeader' => _t('LinkMapping.MAPPEDLINK', 'Mapped Link'),
			'RedirectToHeader' => _t('LinkMapping.REDIRECTTO', 'Redirect To'),
			'RedirectionType'  => _t('LinkMapping.REDIRECTIONTYPE', 'Redirection Type'),
			'RedirectToPage'   => _t('LinkMapping.REDIRTOPAGE', 'Redirect to a Page'),
			'RedirectToLink'   => _t('LinkMapping.REDIRTOLINK', 'Redirect to a Link')
		);
	}

	/**
	 * @return string
	 */
	public function getLink() {

		if ($page = $this->getRedirectPage()) {
			return ($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link();
		} else {
			$link = ($this->LinkType === 'Regular Expression') ?
				preg_replace("|{$this->MappedLink}|i", $this->RedirectLink, $this->matchedURL) : $this->RedirectLink;

			// Prepend the base URL to prevent regular expression forward slashes causing issues.

			return Controller::join_links(Director::baseURL(), $link);
		}
	}


	/**
	 * Retrieve the redirect page associated with this link mapping (where applicable).
	 * @return SiteTree
	 */
	public function getRedirectPage() {

		return ($this->RedirectType === 'Page' && $this->RedirectPageID) ? SiteTree::get_by_id('SiteTree', $this->RedirectPageID) : null;
	}

	/**
	 * Retrieve the stage of this link mapping.
	 * @return string
	 */
	public function getStage() {

		return (($this->RedirectType !== 'Link') && ClassInfo::exists('SiteTree')) ? (
			$this->getRedirectPage() ?
				'Live' : 'Stage'
		) : '-';
	}

	/**
	 * Retrieve the redirect page link associated with this link mapping.
	 * @return string
	 */
	public function getRedirectPageLink() {

		return (($this->RedirectType !== 'Link') && ClassInfo::exists('SiteTree')) ? (
			(($page = $this->getRedirectPage()) && (($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link())) ? MisdirectionService::unify(
				($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link()) : '-'
		) : ($this->RedirectLink ? $this->RedirectLink : '-');
	}

	/**
	 * Retrieve the redirect page title associated with this link mapping (where applicable).
	 * @return string
	 */
	public function getRedirectPageTitle() {

		$page = $this->getRedirectPage();
		return $page ?
			$page->Title : '-';
	}

	/**
	 * Retrieve the hostname restriction associated with this link mapping.
	 * @return string
	 */
	public function getHost() {

		return $this->HostnameRestriction ? $this->HostnameRestriction : '-';
	}

}
