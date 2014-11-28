<?php

/**
 *	Misdirection CMS interface for creating, managing and testing customisable link redirection mappings.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class MisdirectionAdmin extends ModelAdmin {

	private static $managed_models = 'LinkMapping';

	private static $menu_title = 'Misdirection';

	private static $menu_description = 'Create, manage and test customisable <strong>link redirection</strong> mappings.';

	private static $url_segment = 'misdirection';

	private static $allowed_actions = array(
		'getMappingChain'
	);

	/**
	 *	Update the custom summary fields to be sortable.
	 */

	public function getEditForm($ID = null, $fields = null) {

		$form = parent::getEditForm($ID, $fields);
		$gridfield = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));
		$gridfield->getConfig()->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array(
			'RedirectTypeSummary' => 'RedirectType',
			'Host' => 'HostnameRestriction'
		));
		return $form;
	}

	/**
	 *	Display an error page on invalid request.
	 *
	 *	@parameter <{ERROR_CODE}> integer
	 *	@parameter <{ERROR_MESSAGE}> string
	 */

	public function httpError($code, $message = null) {

		// Display the error page for the given status code.

		if(ClassInfo::exists('SiteTree') && ($response = ErrorPage::response_for($code))) {
			throw new SS_HTTPResponse_Exception($response, $code);
		}
		else {
			return parent::httpError($code, $message);
		}
	}

	/**
	 *	Retrieve the JSON link mapping recursion stack for the testing interface.
	 *
	 *	@URLparameter map <{TEST_URL}> string
	 *	@return JSON
	 */

	public function getMappingChain() {

		// Restrict this functionality to administrators.

		$user = Member::currentUserID();
		if(Permission::checkMember($user, 'ADMIN')) {

			// Instantiate a request to handle the link mapping.

			$request = new SS_HTTPRequest('GET', $this->getRequest()->getVar('map'));

			// Retrieve the link mapping recursion stack JSON.

			$testing = true;
			$mappings = singleton('MisdirectionService')->getMappingByRequest($request, $testing);
			$this->getResponse()->addHeader('Content-Type', 'application/json');
			return Convert::array2json($mappings);
		}
		else {
			return $this->httpError(404);
		}
	}

}
