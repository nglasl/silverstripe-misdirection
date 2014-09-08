<?php
/**
 * A link mapping that connects a link to either a redirected link or another
 * page on the site.
 *
 * @package silverstripe-linkmapping
 */
class LinkMapping extends DataObject {

	// Define the redirect page through DB fields if the CMS module doesn't exist.

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

	/**
	 *	Returns a link mapping for a link if one exists, matching any restrictions and priorities that have been set.
	 *
	 *	@param string
	 *	@param string
	 *	@return LinkMapping
	 */

	public static function get_by_link($link, $host = null) {

		$link = self::unify_link(Director::makeRelative($link));
		$linkParts = explode('?', $link);
		$url = Convert::raw2sql($linkParts[0]);

		// Retrieve the link mappings, ordered by query string and priority.

		$matches = LinkMapping::get()->sort(array(
			'Priority' => 'DESC',
			'MappedLink' => 'DESC',
			'ID' => Config::inst()->get('LinkMapping', 'priority')
		));

		// Enforce any hostname restrictions that may have been defined against the link mapping.

		if(is_null($host)) {
			$host = Controller::curr()->getRequest()->getHeader('Host');
		}
		$matches = $matches->where(
			"(HostnameRestriction IS NULL) OR (HostnameRestriction = '" . Convert::raw2sql($host) . "')"
		);

		// Retrieve the matching link mappings depending on the database connection type.

		if(DB::getConn() instanceof MySQLDatabase) {

			// Filter the simple and regular expression link mappings from a database level (currently only limited to MySQL due to the syntax/support http://dev.mysql.com/doc/refman/5.1/en/regexp.html#operator_regexp).

			$matches = $matches->where(
				"((LinkType = 'Simple') AND ((MappedLink = '{$url}') OR (MappedLink LIKE '{$url}?%'))) OR ((LinkType = 'Regular Expression') AND ('{$url}' REGEXP REPLACE(MappedLink, '\\\\', '\\\\\\\\')))"
			);
		}
		else {
			$filtered = ArrayList::create();

			// Filter the simple link mappings from a database level.

			$regexMatches = clone $matches;
			$matches = $matches->where(
				"(LinkType = 'Simple') AND ((MappedLink = '{$url}') OR (MappedLink LIKE '{$url}?%'))"
			);

			// Filter the remaining regular expression link mappings manually.

			$regexMatches = $regexMatches->filter('LinkType', 'Regular Expression');
			foreach($regexMatches as $regexMatch) {
				if(preg_match("|{$regexMatch->MappedLink}|", $url)) {
					$filtered->push($regexMatch);
				}
			}
			$filtered->merge($matches);

			// Make sure the regular expression link mappings are sorted correctly.

			$filtered = $filtered->sort(array(
				'Priority' => 'DESC',
				'MappedLink' => 'DESC',
				'ID' => Config::inst()->get('LinkMapping', 'priority')
			));
			$matches = $filtered;
		}

		// Determine which link mapping should be returned.

		$queryParams = array();
		if(isset($linkParts[1])) {
			parse_str($linkParts[1], $queryParams);
		}
		if($matches->count()) {
			foreach($matches as $match) {

				// Make sure the link mapping matches the current stage, where a staging only link mapping will return 'Live' for only '?stage=Stage'.

				if($match->getStage() !== 'Stage') {

					// Check for a match with the same GET variables in any order, taking the special characters from regular expressions into account.

					$matchQueryString = explode('?', $match->MappedLink);
					if(($match->LinkType === 'Simple') && isset($matchQueryString[1])) {
						$matchParams = array();
						parse_str($matchQueryString[1], $matchParams);

						// Make sure each URL parameter matches against the link mapping.

						if($matchParams == $queryParams){
							$match->setMatchedURL($linkParts[0]);
							return $match;
						}
					}
					else {

						// Otherwise return the first link mapping which matches the current stage.

						$match->setMatchedURL($linkParts[0]);
						return $match;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Unifies a link so mappings are predictable.
	 *
	 * @param  string $link
	 * @return string
	 */
	public static function unify_link($link) {
		return strtolower(trim($link, '/?'));
	}

	/**
	 *	Retrieve the link mapping chain for something such as testing.
	 *	@return array
	 */

	public static function get_link_mapping_chain_by_request(SS_HTTPRequest $request) {

		// Pass the testing flag through so the link mapping chain ends up being returned.

		return self::get_link_mapping_by_request($request, true);
	}

	/**
	 *	Retrieve a link mapping where the URL matches appropriately.
	 *	@return LinkMapping
	 */

	public static function get_link_mapping_by_request(SS_HTTPRequest $request, $testing = false) {

		// Clean up the URL, making sure an external URL comes through correctly.

		$link = $request->getURL(true);
		$link = str_replace(':/', '://', $link);
		$host = $request->getHeader('Host');

		// Retrieve the appropriate link mapping.

		$map = self::get_by_link($link, $host);
		if($map) {

			// Traverse the link mapping chain and return the final link mapping.

			return self::get_recursive_link_mapping($host, $map, $testing);
		}
		return null;
	}

	/**
	 *	Traverse the link mapping chain and return the final link mapping.
	 *	@return string
	 */

	public static function get_recursive_link_mapping($host, LinkMapping $map, $testing = false) {

		// Keep track of the link mapping recursion stack.

		$counter = 1;
		$redirect = $map->getLink();
		$chain = array(
			array_merge(array(
				'Counter' => $counter,
				'RedirectLink' => $redirect
			), $map->toMap())
		);

		// Retrieve the next link mapping if one exists.

		while($next = self::get_by_link($redirect, $host)) {

			// Enforce a maximum number of redirects, preventing inefficient link mappings and infinite recursion.

			if($counter === Config::inst()->get('LinkMappingRequestFilter', 'maximum_requests')) {
				$chain[] = array(
					'ResponseCode' => 404
				);

				// Return the call stack when the testing flag has been set.

				return $testing ? $chain : null;
			}
			$redirect = $next->getLink();
			$chain[] = array_merge(array(
				'Counter' => ++$counter,
				'RedirectLink' => $redirect
			), $next->toMap());
			$map = $next;
		}

		// Return the call stack when the testing flag has been set.

		return $testing ? $chain : $map;
	}

	public function setMatchedURL($matchedURL) {

		$this->matchedURL = $matchedURL;
	}

	public function getCMSFields() {

		$fields = parent::getCMSFields();
		Requirements::css(LINK_MAPPING_PATH . '/css/link-mapping.css');

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
		$this->MappedLink = self::unify_link($this->MappedLink);
		$this->RedirectLink = self::unify_link($this->RedirectLink);
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
			(($page = $this->getRedirectPage()) && (($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link())) ? (
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
