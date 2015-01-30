<?php

/**
 *	Hooks into the current director response and appropriately redirects towards the highest priority link mapping that may have been defined.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class LinkMappingRequestFilter implements RequestFilter {

	public $service;

	private static $dependencies = array(
		'service' => '%$MisdirectionService'
	);

	/**
	 *	The configuration for the default automated URL handling.
	 */

	private static $enforce_misdirection = true;

	private static $replace_default = false;

	/**
	 *	The maximum number of consecutive misdirections.
	 */

	private static $maximum_requests = 9;

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {

		return true;
	}

	/**
	 *	Attempt to redirect towards the highest priority link mapping that may have been defined.
	 *
	 *	@URLparameter direct <{BYPASS_LINK_MAPPINGS}> boolean
	 */

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		// Bypass the request filter when requesting specific director rules such as "/admin" or "/dev".

		$requestURL = $request->getURL();
		foreach(Config::inst()->get('Director', 'rules') as $segment => $controller) {

			// Retrieve the specific director rules.

			if(($position = strpos($segment, '$')) !== false) {
				$segment = rtrim(substr($segment, 0, $position), '/');
			}

			// Determine if the current request matches a specific director rule.

			if($segment && (strpos($requestURL, $segment) === 0)) {

				// Continue processing the response.

				return true;
			}
		}

		// Bypass the request filter when using the direct GET parameter.

		if($request->getVar('direct')) {

			// Continue processing the response.

			return true;
		}

		// Determine the default automated URL handling response status.

		$status = $response->getStatusCode();
		$success = (($status >= 200) && ($status < 300));
		$error = ($status === 404);

		// Either hook into a page not found, or when enforced, replace the default automated URL handling.

		if(($error || Config::inst()->get('LinkMappingRequestFilter', 'enforce_misdirection')) && ($map = $this->service->getMappingByRequest($request))) {

			// Update the response code where appropriate.

			$responseCode = $map->ResponseCode;
			if($responseCode == 0) {
				$responseCode = 303;
			}
			else if(($responseCode == 301) && $map->ForwardPOSTRequest) {
				$responseCode = 308;
			}
			else if(($responseCode == 303) && $map->ForwardPOSTRequest) {
				$responseCode = 307;
			}

			// Update the response using the link mapping redirection.

			$response->redirect($map->getLink(), $responseCode);
		}

		// Determine a page not found fallback, when the CMS module is present.

		else if($error && ($fallback = $this->service->determineFallback($requestURL))) {

			// Update the response code where appropriate.

			$responseCode = $fallback['code'];
			if($responseCode === 0) {
				$responseCode = 303;
			}

			// Update the response using the fallback, enforcing no further redirection.

			$response->redirect(HTTP::setGetVar('direct', true, Controller::join_links(Director::absoluteBaseURL(), $fallback['link'])), $responseCode);
		}

		// When enabled, replace the default automated URL handling with a page not found.

		else if(!$error && !$success && Config::inst()->get('LinkMappingRequestFilter', 'replace_default')) {
			$response->setStatusCode(404);

			// Retrieve the appropriate page not found response.

			(ClassInfo::exists('SiteTree') && ($page = ErrorPage::response_for(404))) ? $response->setBody($page->getBody()) : $response->setBody('No URL was matched!');
		}

		// Continue processing the response.

		return true;
	}

}
