<?php

/**
 *	Misdirection CMS interface for creating, managing and testing customisable link redirection mappings.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
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
			'RedirectTypeSummary' => 'RedirectType'
		));

		// Allow extension customisation.

		$this->extend('updateMisdirectionAdminEditForm', $form);
		return $form;
	}

	/**
	 *	Display an error page on invalid request.
	 *
	 *	@parameter <{ERROR_CODE}> integer
	 *	@parameter <{ERROR_MESSAGE}> string
	 */

	public function httpError($code, $message = null) {

		// Determine the error page for the given status code.

		$errorPages = ClassInfo::exists('SiteTree') ? ErrorPage::get()->filter('ErrorCode', $code) : null;

		// Allow extension customisation.

		$this->extend('updateErrorPages', $errorPages);

		// Retrieve the error page response.

		if($errorPages && ($errorPage = $errorPages->first())) {
			Requirements::clear();
			Requirements::clear_combined_files();
			$response = ModelAsController::controller_for($errorPage)->handleRequest(new SS_HTTPRequest('GET', ''), DataModel::inst());
			throw new SS_HTTPResponse_Exception($response, $code);
		}

		// Retrieve the cached error page response.

		else if($errorPages && file_exists($cachedPage = ErrorPage::get_filepath_for_errorcode($code, class_exists('Translatable') ? Translatable::get_current_locale() : null))) {
			$response = new SS_HTTPResponse();
			$response->setStatusCode($code);
			$response->setBody(file_get_contents($cachedPage));
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

			// JSON_PRETTY_PRINT.

			return json_encode($mappings, 128);
		}
		else {
			return $this->httpError(404);
		}
	}

    /**
     * Export all domain model fields, instead of display fields
     * @return array all fields in the model
     */
    public function getExportFields()
    {
        $fields = array();
        $fields['LinkType'] = 'LinkType';
        $fields['MappedLink'] = 'MappedLink';
        $fields['IncludesHostname'] = 'IncludesHostname';
        $fields['Priority'] = 'Priority';
        $fields['RedirectType'] = 'RedirectType';
        $fields['RedirectLink'] = 'RedirectLink';
        $fields['RedirectPageID'] = 'RedirectPageID';
        $fields['ResponseCode'] = 'ResponseCode';
        $fields['ForwardPOSTRequest'] = 'ForwardPOSTRequest';
        $fields['HostnameRestriction'] = 'HostnameRestriction';

        return $fields;
    }


}
