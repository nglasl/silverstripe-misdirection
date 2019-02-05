<?php

/**
 *	This extension provides vanity mapping directly from a page, and automatically creates the appropriate link mappings when replacing the default automated URL handling.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class SiteTreeMisdirectionExtension extends DataExtension {

	/**
	 *	This provides link mapping customisation directly from a page.
	 */

	private static $has_one = array(
		'VanityMapping' => 'LinkMapping'
	);

	public function updateSettingsFields($fields) {

		$fields->addFieldToTab('Root.Misdirection', HeaderField::create(
			'VanityHeader',
			'Vanity'
		));
		if($this->owner->VanityMapping()->RedirectPageID !== $this->owner->ID) {

			// The mapping may have been pointed to another page.

			$this->owner->VanityMappingID = 0;
		}
		$fields->addFieldToTab('Root.Misdirection', TextField::create(
			'VanityURL',
			'URL',
			$this->owner->VanityMapping()->MappedLink
		)->setRightTitle('Mappings with higher priority will take precedence over this'));

		// Allow extension customisation.

		$this->owner->extend('updateSiteTreeMisdirectionExtensionSettingsFields', $fields);
	}

	public function validate(ValidationResult $result) {

		// Retrieve the vanity mapping URL, where this is only possible using the POST variable.

		$vanityURL = (!Controller::has_curr() || is_null($controller = Controller::curr()) || is_null($URL = $controller->getRequest()->postVar('VanityURL'))) ? $this->owner->VanityMapping()->MappedLink : $URL;

		// Determine whether another vanity mapping already exists.
		
		$existing = LinkMapping::get()->filter(array(
			'MappedLink' => $vanityURL,
			'RedirectType' => 'Page',
			'RedirectPageID:not' => array(
				0,
				$this->owner->ID
			)
		))->first();
		if($result->valid() && $existing && ($page = $existing->getRedirectPage())) {
			$link = Controller::join_links(CMSPageSettingsController::singleton()->Link('show'), $page->ID);
			$result->error('Vanity URL already exists!');
		}

		// Allow extension.

		$this->owner->extend('validateSiteTreeMisdirectionExtension', $result);
		return $result;
	}

	/**
	 *	Update the corresponding vanity mapping.
	 */

	public function onBeforeWrite() {

		parent::onBeforeWrite();

		// Retrieve the vanity mapping URL, where this is only possible using the POST variable.

		$vanityURL = (!Controller::has_curr() || is_null($controller = Controller::curr()) || is_null($URL = $controller->getRequest()->postVar('VanityURL'))) ? $this->owner->VanityMapping()->MappedLink : $URL;
		$mappingExists = $this->owner->VanityMapping()->exists();

		// Determine whether the vanity mapping URL has been updated.

		if($vanityURL && $mappingExists) {
			if($this->owner->VanityMapping()->MappedLink !== $vanityURL) {

				// Update the corresponding vanity mapping.

				$this->owner->VanityMapping()->MappedLink = $vanityURL;
				$this->owner->VanityMapping()->write();
			}
		}

		// Determine whether the vanity mapping URL has been defined.

		else if($vanityURL) {

			// Instantiate the vanity mapping.

			$mapping = singleton('MisdirectionService')->createPageMapping($vanityURL, $this->owner->ID, 2);
			$this->owner->VanityMappingID = $mapping->ID;
		}

		// Determine whether the vanity mapping URL has been removed.

		else if($mappingExists) {

			// Remove the corresponding vanity mapping.

			$this->owner->VanityMapping()->delete();
		}
	}

	/**
	 *	Update link mappings when replacing the default automated URL handling.
	 */

	public function onAfterWrite() {

		parent::onAfterWrite();

		// Determine whether the default automated URL handling has been replaced.

		if(Config::inst()->get('MisdirectionRequestFilter', 'replace_default')) {

			// Determine whether the URL segment or parent ID has been updated.

			$changed = $this->owner->getChangedFields();
			if((isset($changed['URLSegment']['before']) && isset($changed['URLSegment']['after']) && ($changed['URLSegment']['before'] != $changed['URLSegment']['after'])) || (isset($changed['ParentID']['before']) && isset($changed['ParentID']['after']) && ($changed['ParentID']['before'] != $changed['ParentID']['after']))) {

				// The link mappings should only be created for existing pages.

				$URL = (isset($changed['URLSegment']['before']) ? $changed['URLSegment']['before'] : $this->owner->URLSegment);
				if(strpos($URL, 'new-') !== 0) {

					// Determine the page URL.

					$parentID = (isset($changed['ParentID']['before']) ? $changed['ParentID']['before'] : $this->owner->ParentID);
					$parent = SiteTree::get_one('SiteTree', "SiteTree.ID = {$parentID}");
					while($parent) {
						$URL = Controller::join_links($parent->URLSegment, $URL);
						$parent = SiteTree::get_one('SiteTree', "SiteTree.ID = {$parent->ParentID}");
					}

					// Instantiate a link mapping for this page.

					singleton('MisdirectionService')->createPageMapping($URL, $this->owner->ID);

					// Purge any link mappings that point back to the same page.

					$this->owner->regulateMappings(($this->owner->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $this->owner->Link(), $this->owner->ID);

					// Recursively create link mappings for any children.

					$children = $this->owner->AllChildrenIncludingDeleted();
					if($children->count()) {
						$this->owner->recursiveMapping($URL, $children);
					}
				}
			}
		}
	}

	/**
	 *	Determine whether link mappings need to be updated when removing this page.
	 */

	public function onAfterDelete() {

		parent::onAfterDelete();

		// Determine whether this page has been completely removed.

		if(Config::inst()->get('MisdirectionRequestFilter', 'replace_default') && !$this->owner->isPublished() && $this->owner->getIsDeletedFromStage()) {

			// Convert any link mappings that are directly associated with this page.

			$mappings = LinkMapping::get()->filter(array(
				'RedirectType' => 'Page',
				'RedirectPageID' => $this->owner->ID
			));
			foreach($mappings as $mapping) {
				$mapping->RedirectType = 'Link';
				$mapping->RedirectLink = Director::makeRelative(($this->owner->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $this->owner->Link());
				$mapping->write();
			}
		}
	}

	/**
	 *	Purge any link mappings that point back to the same page.
	 *
	 *	@parameter <{PAGE_URL}> string
	 *	@parameter <{PAGE_ID}> integer
	 */

	public function regulateMappings($pageLink, $pageID) {

		LinkMapping::get()->filter(array(
			'MappedLink' => MisdirectionService::unify_URL(Director::makeRelative($pageLink)),
			'RedirectType' => 'Page',
			'RedirectPageID' => $pageID
		))->removeAll();
	}

	/**
	 *	Recursively create link mappings for any children.
	 *
	 *	@parameter <{BASE_URL}> string
	 *	@parameter <{PAGE_CHILDREN}> array(site tree)
	 */

	public function recursiveMapping($baseURL, $children) {

		foreach($children as $child) {

			// Instantiate a link mapping for this page.

			$URL = Controller::join_links($baseURL, $child->URLSegment);
			singleton('MisdirectionService')->createPageMapping($URL, $child->ID);

			// Purge any link mappings that point back to the same page.

			$this->owner->regulateMappings(($child->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $child->Link(), $child->ID);

			// Recursively create link mappings for any children.

			$recursiveChildren = $child->AllChildrenIncludingDeleted();
			if($recursiveChildren->count()) {
				$this->owner->recursiveMapping($URL, $recursiveChildren);
			}
		}
	}

}
