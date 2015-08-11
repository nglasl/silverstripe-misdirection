<?php

/**
 *	This extension automatically creates the appropriate link mappings when a page is updated, and also provides vanity mapping directly from a page.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class SiteTreeMisdirectionExtension extends DataExtension {

	/**
	 *	This provides link mapping customisation directly from a page.
	 */

	private static $has_one = array(
		'VanityMapping' => 'LinkMapping'
	);

	/**
	 *	Display the vanity mapping fields.
	 */

	public function updateSettingsFields($fields) {

		$fields->addFieldToTab('Root.Misdirection', HeaderField::create(
			'VanityHeader',
			'Vanity'
		));
		$fields->addFieldToTab('Root.Misdirection', TextField::create(
			'VanityURL',
			'URL',
			$this->owner->VanityMapping()->MappedLink
		)->setRightTitle('Mappings with higher priority will take precedence over this'));
	}

	/**
	 *	Update the corresponding vanity mapping.
	 */

	public function onBeforeWrite() {

		parent::onBeforeWrite();

		// Retrieve the vanity mapping URL, where this is only possible using the POST variable.

		$vanityURL = (($URL = Controller::curr()->getRequest()->postVar('VanityURL')) ? $URL : $this->owner->VanityMapping()->MappedLink);
		$mappingExists = $this->owner->VanityMapping()->exists();

		// Determine whether the vanity mapping URL has been updated.

		if($vanityURL && $mappingExists) {
			if($this->owner->VanityMapping()->MappedLink !== $vanityURL) {

				// Update the corresponding vanity mapping.

				$this->owner->VanityMapping()->MappedLink = $vanityURL;
				$this->owner->VanityMapping()->write();
			}
		}

		// Determine whether the vanity mapping URL has been removed.

		else if($mappingExists) {

			// Remove the corresponding vanity mapping.

			$this->owner->VanityMapping()->delete();
		}

		// Determine whether the vanity mapping URL has been defined.

		else if($vanityURL) {

			// Instantiate the vanity mapping.

			$mapping = singleton('MisdirectionService')->createPageMapping($vanityURL, $this->owner->ID, 2);
			$this->owner->VanityMappingID = $mapping->ID;
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

					$this->owner->purgeRecursiveLinkMappings(($this->owner->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $this->owner->Link(), $this->owner->ID);

					// Recursively create link mappings for any children.

					$children = $this->owner->AllChildrenIncludingDeleted();
					if($children->count()) {
						$this->owner->recursiveLinkMapping($URL, $children);
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

	public function purgeRecursiveLinkMappings($pageLink, $pageID) {

		LinkMapping::get()->filter(array(
			'MappedLink' => MisdirectionService::unify(Director::makeRelative($pageLink)),
			'RedirectType' => 'Page',
			'RedirectPageID' => $pageID
		))->removeAll();
	}

	/**
	 *	Recursively create link mappings for any children.
	 *
	 *	@parameter <{BASE_URL}> string
	 *	@parameter <{CHILDREN}> array(site tree)
	 */

	public function recursiveLinkMapping($baseURL, $children) {

		foreach($children as $child) {

			// Instantiate a link mapping for this page.

			$URL = Controller::join_links($baseURL, $child->URLSegment);
			singleton('MisdirectionService')->createPageMapping($URL, $child->ID);

			// Purge any link mappings that point back to the same page.

			$this->owner->purgeRecursiveLinkMappings(($child->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $child->Link(), $child->ID);

			// Recursively create link mappings for any children.

			$recursiveChildren = $child->AllChildrenIncludingDeleted();
			if($recursiveChildren->count()) {
				$this->owner->recursiveLinkMapping($URL, $recursiveChildren);
			}
		}
	}

}
