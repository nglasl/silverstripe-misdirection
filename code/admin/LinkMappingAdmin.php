<?php
/**
 * A simple administration interface to allow administrators to manage link
 * mappings.
 *
 * @package silverstripe-linkmapping
 */
class LinkMappingAdmin extends ModelAdmin {

	private static $menu_title = 'Link Mappings';
	private static $url_segment = 'link-mappings';
	private static $managed_models = 'LinkMapping';

	/**
	 *	Retrieve the JSON link mapping by AJAX request for testing purposes.
	 */

	private static $allowed_actions = array(
		'getLinkMappingChain'
	);

	/**
	 *	@GETparam <map> string
	 */

	public function getLinkMappingChain() {

		// Construct the link mapping request to test.

		$request = clone $this->getRequest();
		$request->setUrl($request->getVar('map'));
		$mappings = LinkMapping::get_link_mapping_chain_by_request($request);
		return Convert::array2json($mappings);
	}

}
