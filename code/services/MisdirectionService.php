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

	public static function unify($URL) {

		return strtolower(trim($URL, '/?'));
	}

	/**
	 *	Retrieve the appropriate link mapping for a request, with the ability to enable testing and return the recursion stack.
	 *
	 *	@parameter <{REQUEST}> ss http request
	 *	@parameter <{RETURN_STACK}> boolean
	 *	@return link mapping
	 */

	public function getMappingByRequest($request, $testing = false) {

		// Make sure an external URL comes through correctly.

		$link = str_replace(':/', '://', $request->getURL(true));
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

		$URL = self::unify(Director::makeRelative($URL));
		$parts = explode('?', $URL);
		$base = Convert::raw2sql($parts[0]);

		// Instantiate the link mapping query.

		$matches = LinkMapping::get();

		// Enforce any hostname restriction that may have been defined.

		if(is_null($host)) {
			$host = Controller::curr()->getRequest()->getHeader('Host');
		}
		$matches = $matches->where("(HostnameRestriction IS NULL) OR (HostnameRestriction = '" . Convert::raw2sql($host) . "')");

		// Retrieve the link mappings based on the database connection type.

		if(DB::getConn() instanceof MySQLDatabase) {

			// Determine the simple and regular expression matching from the database for MySQL (http://dev.mysql.com/doc/refman/5.1/en/regexp.html#operator_regexp).

			$matches = $matches->where("((LinkType = 'Simple') AND ((MappedLink = '{$base}') OR (MappedLink LIKE '{$base}?%'))) OR ((LinkType = 'Regular Expression') AND ('{$base}' REGEXP REPLACE(MappedLink, '\\\\', '\\\\\\\\')))");
		}
		else {
			$filtered = ArrayList::create();

			// Determine the simple matching from the database.

			$regexMatches = clone $matches;
			$matches = $matches->where("(LinkType = 'Simple') AND ((MappedLink = '{$base}') OR (MappedLink LIKE '{$base}?%'))");

			// Determine the remaining regular expression matching.

			$regexMatches = $regexMatches->filter('LinkType', 'Regular Expression');
			foreach($regexMatches as $regexMatch) {
				if(preg_match("|{$regexMatch->MappedLink}|", $base)) {
					$filtered->push($regexMatch);
				}
			}
			$filtered->merge($matches);
			$matches = $filtered;
		}

		// Make sure the link mappings are ordered by priority and specificity.

		$matches = $matches->sort(array(
			'Priority' => 'DESC',
			'MappedLink' => 'DESC',
			'ID' => Config::inst()->get('LinkMapping', 'priority')
		));

		// Determine which link mapping should be returned, based on the sort order.

		$queryParameters = array();
		if(isset($parts[1])) {
			parse_str($parts[1], $queryParameters);
		}
		foreach($matches as $match) {

			// Make sure the link mapping matches the current stage.

			if($match->getStage() !== 'Stage') {

				// Ignore GET parameter matching for regular expressions, considering the special characters.

				$matchParts = explode('?', $match->MappedLink);
				if(($match->LinkType === 'Simple') && isset($matchParts[1])) {

					// Make sure the GET parameters match in any order.

					$matchParameters = array();
					parse_str($matchParts[1], $matchParameters);
					if($matchParameters == $queryParameters) {
						$match->setMatchedURL($parts[0]);
						return $match;
					}
				}
				else {

					// Return the first link mapping when GET parameters aren't present.

					$match->setMatchedURL($parts[0]);
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
		$chain = array(array_merge(array(
			'Counter' => $counter,
			'RedirectLink' => $redirect
		), $map->toMap()));

		// Determine the next link mapping.

		while($next = $this->getMapping($redirect, $host)) {

			// Enforce a maximum number of redirects, preventing infinite recursion and inefficient link mappings.

			if($counter === Config::inst()->get('LinkMappingRequestFilter', 'maximum_requests')) {
				$chain[] = array(
					'ResponseCode' => 404
				);

				// Return the call stack when testing has been enabled.

				return $testing ? $chain : null;
			}
			$redirect = $next->getLink();
			$chain[] = array_merge(array(
				'Counter' => ++$counter,
				'RedirectLink' => $redirect
			), $next->toMap());
			$map = $next;
		}

		// Return either the call stack when testing has been enabled, or the eventual link mapping result.

		return $testing ? $chain : $map;
	}

	/**
	 *	Instantiate a new link mapping, redirecting a URL towards a site tree element.
	 *
	 *	@parameter <{MAPPING_URL}> string
	 *	@parameter <{MAPPING_PAGE_ID}> integer
	 *	@parameter <{MAPPING_PRIORITY}> integer
	 *	@return link mapping
	 */

	public function createMapping($URL, $redirectID, $priority = 1) {

		// Retrieve an already existing link mapping if one exists.

		$existing = LinkMapping::get()->filter(array(
			'MappedLink' => $URL,
			'RedirectPageID' => $redirectID
		))->first();
		if($existing) {
			return $existing;
		}

		// Instantiate the new link mapping with appropriate default values.

		$mapping = LinkMapping::create();
		$mapping->MappedLink = $URL;
		$mapping->RedirectType = 'Page';
		$mapping->RedirectPageID = $redirectID;
		$mapping->Priority = $priority;
		$mapping->write();
		return $mapping;
	}

}
