<?php

/**
 *	Handles the creation of link mappings, while providing any additional functionality required by the module.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class MisdirectionService {

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
