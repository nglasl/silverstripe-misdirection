<?php

/**
 *	Handles the link mapping recursion to return the eventual result, while providing any additional functionality required by the module.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class MisdirectionService {

	/**
	 *	Unifies a URL so link mappings are predictable.
	 *
	 *	@parameter <{URL}> string
	 *	@return string
	 */

	public static function unify_URL($URL) {

		return strtolower(trim($URL, ' ?/'));
	}

	/**
	 *	Use third party validation to determine an external URL (https://gist.github.com/dperini/729294 and http://mathiasbynens.be/demo/url-regex).
	 *
	 *	@parameter <{URL}> string
	 *	@return boolean
	 */

	public static function is_external_URL($URL) {

		$URL = trim($URL, '/?!"#$%&\'()*+,-.@:;<=>[\\]^_`{|}~');
		$URL = preg_replace('(\s)', '%20', $URL);
		return preg_match('%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu', $URL);
	}

	/**
	 *	Retrieve the appropriate link mapping for a request, with the ability to enable testing and return the recursion stack.
	 *
	 *	@parameter <{REQUEST}> ss http request
	 *	@parameter <{RETURN_STACK}> boolean
	 *	@return link mapping
	 */

	public function getMappingByRequest($request, $testing = false) {

		// Make sure a URL comes through correctly.

		$link = str_replace(array(
			':/',
			' '
		), array(
			'://',
			'%20'
		), $request->getURL(true));
		$host = $request->getHeader('Host');

		// Retrieve the appropriate link mapping.

		$map = $this->getMapping($link, $host);

		// Traverse the link mapping chain and return the eventual result, preventing multiple redirections.

		return $map ? $this->getRecursiveMapping($map, $host, $testing) : null;
	}

	/**
	 *	Retrieve the appropriate link mapping for a URL.
	 *
	 *	@parameter <{URL}> string
	 *	@parameter <{HOSTNAME}> string
	 *	@return link mapping
	 */

	public function getMapping($URL, $host = null) {

		$URL = self::is_external_URL($URL) ? parse_url($URL, PHP_URL_PATH) : Director::makeRelative($URL);
		$URL = self::unify_URL($URL);
		$parts = explode('?', $URL);

		// Instantiate the link mapping query.

		$matches = LinkMapping::get();

		// Enforce any hostname restriction that may have been defined.

		if(is_null($host) && ($controller = Controller::curr())) {
			$host = $controller->getRequest()->getHeader('Host');
		}
		$temporary = $host;
		$host = Convert::raw2sql($host);
		$matches = $matches->where("(HostnameRestriction IS NULL) OR (HostnameRestriction = '{$host}')");
		$regex = clone $matches;

		// Determine the simple matching from the database.

		$base = Convert::raw2sql($parts[0]);
		$matches = $matches->where("(LinkType = 'Simple') AND (((IncludesHostname = 0) AND ((MappedLink = '{$base}') OR (MappedLink LIKE '{$base}?%'))) OR ((IncludesHostname = 1) AND ((MappedLink = '{$host}/{$base}') OR (MappedLink LIKE '{$host}/{$base}?%'))))");
		$host = $temporary;

		// Determine the remaining regular expression matching, as this is inconsistent from the database.

		$regex = $regex->filter('LinkType', 'Regular Expression');
		$filtered = ArrayList::create();
		foreach($regex as $match) {
			if((!$match->IncludesHostname && preg_match("%{$match->MappedLink}%", $URL)) || ($match->IncludesHostname && preg_match("%{$match->MappedLink}%", "{$host}/{$URL}"))) {
				$filtered->push($match);
			}
		}
		$filtered->merge($matches);
		$matches = $filtered;

		// Make sure the link mappings are ordered by priority and specificity.

		$matches = $matches->sort(array(
			'Priority' => 'DESC',
			'LinkType' => 'DESC',
			'MappedLink' => 'DESC',
			'ID' => Config::inst()->get('LinkMapping', 'priority')
		));

		// Determine which link mapping should be returned, based on the sort order.

		$queryParameters = array();
		if(isset($parts[1])) {
			parse_str($parts[1], $queryParameters);
		}
		foreach($matches as $match) {

			// Make sure the link mapping is live on the current stage.

			if($match->isLive() !== 'false') {

				// Ignore GET parameter matching for regular expressions, considering the special characters.

				$matchParts = explode('?', $match->MappedLink);
				if(($match->LinkType === 'Simple') && isset($matchParts[1])) {

					// Make sure the GET parameters match in any order.

					$matchParameters = array();
					parse_str($matchParts[1], $matchParameters);
					if($matchParameters == $queryParameters) {
						return $match;
					}
				}
				else {

					// Return the first link mapping when GET parameters aren't present.

					$match->setMatchedURL($match->IncludesHostname ? "{$host}/{$URL}" : $URL);
					return $match;
				}
			}
		}

		// No mapping has been found.

		return null;
	}

	/**
	 *	Traverse the link mapping chain and return the eventual result, preventing multiple redirections.
	 *
	 *	@parameter <{LINK_MAPPING}> link mapping
	 *	@parameter <{HOSTNAME}> string
	 *	@parameter <{RETURN_STACK}> boolean
	 *	@return link mapping/array
	 */

	public function getRecursiveMapping($map, $host = null, $testing = false) {

		// Keep track of the link mapping recursion.

		$counter = 1;
		$redirect = $map->getLink();
		$chain = array(
			array_merge($map->toMap(), array(
				'Counter' => $counter,
				'RedirectLink' => $map->getLinkSummary(),
				'LinkMapping' => $map
			))
		);

		// Determine the subsequent host.

		if($map->getLinkHost()) {
			$host = $map->getLinkHost();
		}

		// Determine the next link mapping, immediately redirecting towards an external URL.

		while((($map->RedirectType === 'Page') || !self::is_external_URL($redirect)) && ($next = $this->getMapping($redirect, $host))) {

			// Enforce a maximum number of redirects, preventing infinite recursion and inefficient link mappings.

			if($counter === Config::inst()->get('MisdirectionRequestFilter', 'maximum_requests')) {
				$chain[] = array(
					'ResponseCode' => 404
				);

				// Return the call stack when testing has been enabled.

				return $testing ? $chain : null;
			}
			$redirect = $next->getLink();
			$chain[] = array_merge($next->toMap(), array(
				'Counter' => ++$counter,
				'RedirectLink' => $next->getLinkSummary(),
				'LinkMapping' => $next
			));

			// Determine the subsequent host.

			if($next->getLinkHost()) {
				$host = $next->getLinkHost();
			}
			$map = $next;
		}

		// Return either the call stack when testing has been enabled, or the eventual link mapping result.

		return $testing ? $chain : $map;
	}

	/**
	 *	Determine the fallback for a URL when the CMS module is present.
	 *
	 *	@parameter <{URL}> string
	 *	@return array(string, integer)
	 */

	public function determineFallback($URL) {

		// Make sure the CMS module is present.

		if(ClassInfo::exists('SiteTree') && $URL) {

			// Instantiate the required variables.

			$segments = explode('/', self::unify_URL($URL));
			$applicableRule = null;
			$nearestParent = null;
			$thisPage = null;
			$toURL = null;
			$responseCode = 303;

			// This prevents a page not found from redirecting back to the same page.

			array_pop($segments);

			// Retrieve the default site configuration fallback.

			$config = SiteConfig::current_site_config();
			if($config && $config->Fallback) {
				$applicableRule = $config->Fallback;
				$nearestParent = $thisPage = Director::baseURL();
				$toURL = $config->FallbackLink;
				$responseCode = $config->FallbackResponseCode;
			}

			// This is required to support multiple sites.

			if(ClassInfo::exists('Multisites')) {
				$parent = Multisites::inst()->getCurrentSite();
				$parentID = $parent->ID;
				$applicableRule = $parent->Fallback;
				$toURL = $parent->FallbackLink;
				$responseCode = $parent->FallbackResponseCode;
			}
			else {
				$parentID = 0;
			}

			// Determine the page specific fallback.

			for($iteration = 0; $iteration < count($segments); $iteration++) {
				$page = SiteTree::get()->filter(array(
					'URLSegment' => $segments[$iteration],
					'ParentID' => $parentID
				))->first();
				if($page) {

					// Determine the home page URL when appropriate.

					$link = ($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link();
					$nearestParent = $link;

					// Keep track of the current page fallback.

					if($page->Fallback) {
						$applicableRule = $page->Fallback;
						$thisPage = $link;
						$toURL = $page->FallbackLink;
						$responseCode = $page->FallbackResponseCode;
					}
					$parentID = $page->ID;
				}
				else {

					// The bottom of the chain has been reached.

					break;
				}
			}

			// Determine the applicable fallback.

			if($applicableRule) {
				$link = null;
				switch($applicableRule) {

					// Bypass the request filter.

					case 'Nearest':
						$link = '/' . HTTP::setGetVar('misdirected', true, $nearestParent);
						break;
					case 'This':
						$link = '/' . HTTP::setGetVar('misdirected', true, $thisPage);
						break;
					case 'URL':

						// When appropriate, prepend the base URL to match a page redirection.

						$link = self::is_external_URL($toURL) ? (ClassInfo::exists('Multisites') ? HTTP::setGetVar('misdirected', true, $toURL) : $toURL) : ('/' . HTTP::setGetVar('misdirected', true, Controller::join_links(Director::baseURL(), $toURL)));
						break;
				}
				if($link) {
					return array(
						'link' => $link,
						'code' => (int)$responseCode
					);
				}
			}
		}

		// No fallback has been found.

		return null;
	}

	/**
	 *	Instantiate a new link mapping, redirecting a URL towards a page.
	 *
	 *	@parameter <{MAPPING_URL}> string
	 *	@parameter <{MAPPING_PAGE_ID}> integer
	 *	@parameter <{MAPPING_PRIORITY}> integer
	 *	@return link mapping
	 */

	public function createPageMapping($URL, $redirectID, $priority = 1) {

		// Retrieve an already existing link mapping if one exists.

		$existing = LinkMapping::get()->filter(array(
			'MappedLink' => $URL,
			'RedirectType' => 'Page',
			'RedirectPageID' => $redirectID
		))->first();
		if($existing) {
			return $existing;
		}

		// Instantiate the new link mapping with appropriate default values.

		$mapping = LinkMapping::create();
		$mapping->MappedLink = $URL;
		$mapping->RedirectType = 'Page';
		$mapping->RedirectPageID = (int)$redirectID;
		$mapping->Priority = (int)$priority;
		$mapping->write();
		return $mapping;
	}

	/**
	 *	Instantiate a new link mapping, redirecting a URL towards another URL.
	 *
	 *	@parameter <{MAPPING_URL}> string
	 *	@parameter <{MAPPING_REDIRECT_URL}> string
	 *	@parameter <{MAPPING_PRIORITY}> integer
	 *	@return link mapping
	 */

	public function createURLMapping($URL, $redirectURL, $priority = 1) {

		// Retrieve an already existing link mapping if one exists.

		$existing = LinkMapping::get()->filter(array(
			'MappedLink' => $URL,
			'RedirectType' => 'Link',
			'RedirectLink' => $redirectURL
		))->first();
		if($existing) {
			return $existing;
		}

		// Instantiate the new link mapping with appropriate default values.

		$mapping = LinkMapping::create();
		$mapping->MappedLink = $URL;
		$mapping->RedirectType = 'Link';
		$mapping->RedirectLink = $redirectURL;
		$mapping->Priority = (int)$priority;
		$mapping->write();
		return $mapping;
	}

}
