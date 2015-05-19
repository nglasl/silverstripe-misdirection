<?php

/**
 *	Automatically create a link mapping when a site tree URL segment or parent ID has been updated.
 *
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class MisdirectionSiteTreeExtension extends DataExtension {

	// Allow direct link mapping customisation from the pages themselves.

	private static $has_one = array(
		'VanityMapping' => 'LinkMapping'
	);
	
	public function updateSettingsFields(FieldList $fields) {

		// Allow direct link mapping customisation using a vanity URL.

		$fields->addFieldToTab('Root.Misdirection', HeaderField::create(
			'VanityHeader',
			_t('LinkMapping.VanityHeader', 'Shortcut')
		));
		$vanityInfo = _t('LinkMapping.VANITY_DETAILS', 'Matching link mappings with higher priority will take precedence over this');
		$fields->addFieldToTab('Root.Misdirection', TextField::create(
			'VanityURL',
			'Vanity URL',
			$this->owner->VanityMapping()->MappedLink
		)->setRightTitle($vanityInfo));
	}

	public function onBeforeWrite() {

		// Retrieve the post variable here since $this->owner->VanityURL does not work.

		$vanityURL = ($URL = Controller::curr()->getRequest()->postVar('VanityURL')) ?
			$URL : $this->owner->VanityMapping()->MappedLink;
		$mappingExists = $this->owner->VanityMapping()->exists();

		// Update the existing link mapping using the user defined vanity URL.

		if($vanityURL && $mappingExists) {

			// Make sure the vanity URL has actually been updated.

			if($this->owner->VanityMapping()->MappedLink !== $vanityURL) {

				// Update the link mapping data object.

				$this->owner->VanityMapping()->MappedLink = $vanityURL;
				$this->owner->VanityMapping()->write();
			}
		}

		// Remove the existing link mapping when the user defined vanity URL is found blank.

		else if($mappingExists) {

			// Remove the link mapping data object.

			$this->owner->VanityMapping()->delete();
		}

		// Instantiate the direct link mapping when the user defined vanity URL has been defined.

		else if($vanityURL) {

			// Instantiate a new link mapping data object, or retrieve an existing one which matches.

			$mapping = singleton('MisdirectionService')->createPageMapping($vanityURL, $this->owner->ID, 2);
			$this->owner->VanityMappingID = $mapping->ID;
		}
	}

	public function onAfterWrite() {

		parent::onAfterWrite();
		if(Config::inst()->get('MisdirectionRequestFilter', 'replace_default')) {

			// Make sure that the URL segment or parent ID has been updated.

			$changed = $this->owner->getChangedFields();
			if((isset($changed['URLSegment']['before']) && isset($changed['URLSegment']['after']) && ($changed['URLSegment']['before'] != $changed['URLSegment']['after']))
				|| (isset($changed['ParentID']['before']) && isset($changed['ParentID']['after']) && ($changed['ParentID']['before'] != $changed['ParentID']['after']))) {

				// Make sure we don't create a link mapping for newly created pages.

				$URLsegment = isset($changed['URLSegment']['before']) ? $changed['URLSegment']['before'] : $this->owner->URLSegment;
				if($URLsegment !== 'new-page') {

					// Construct the URL to be used for the link mapping.

					$parentID = isset($changed['ParentID']['before']) ? $changed['ParentID']['before'] : $this->owner->ParentID;
					$parent = SiteTree::get_one('SiteTree', "SiteTree.ID = {$parentID}");
					while($parent) {
						$URLsegment = Controller::join_links($parent->URLSegment, $URLsegment);
						$parent = SiteTree::get_one('SiteTree', "SiteTree.ID = {$parent->ParentID}");
					}

					// Create a link mapping for this site tree element.

					singleton('MisdirectionService')->createPageMapping($URLsegment, $this->owner->ID);

					// Purge any recursive link mappings where the redirect link now points back to the mapped link.

					$this->purgeRecursiveLinkMappings(($this->owner->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $this->owner->Link(), $this->owner->ID);

					// Recursively create link mappings for any children of this site tree element.

					$children = $this->owner->AllChildrenIncludingDeleted();
					if($children->count()) {
						$this->recursiveLinkMapping($URLsegment, $children);
					}
				}
			}
		}
	}

	public function onAfterDelete() {

		parent::onAfterDelete();

		// When this site tree element has been removed from both staging and live.

		if(Config::inst()->get('MisdirectionRequestFilter', 'replace_default') && $this->owner->getIsDeletedFromStage() && !$this->owner->isPublished()) {

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
	 *	Recursively create link mappings for any site tree children.
	 *	@param string
	 *	@param ArrayList
	 */

	public function recursiveLinkMapping($baseURL, $children) {

		foreach($children as $child) {
			$URLsegment = Controller::join_links($baseURL, $child->URLSegment);
			singleton('MisdirectionService')->createPageMapping($URLsegment, $child->ID);

			// Purge any recursive link mappings where the redirect link now points back to the mapped link.

			$this->purgeRecursiveLinkMappings(($child->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $child->Link(), $child->ID);

			// Recursively create link mappings for any children of this child.

			$recursiveChildren = $child->AllChildrenIncludingDeleted();
			if($recursiveChildren->count()) {
				$this->recursiveLinkMapping($URLsegment, $recursiveChildren);
			}
		}
	}

	/**
	 *	Purge any recursive link mappings where the redirect link now points back to the mapped link.
	 *	@param <LINKED_PAGE_URL> string
	 *	@param integer
	 */

	public function purgeRecursiveLinkMappings($pageLink, $redirectPageID) {

		// Make sure the page redirect link matches the mapped link before purging.

		LinkMapping::get()->filter(array(
			'MappedLink' => MisdirectionService::unify(Director::makeRelative($pageLink)),
			'RedirectType' => 'Page',
			'RedirectPageID' => $redirectPageID
		))->removeAll();
	}

}
