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

	private static $replace_default = false;

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

		// Bypass the request filter when using the GET parameter.

		if($request->getVar('direct')) {
			return true;
		}

		// Either hook into a page not found or replace the default automated URL handling.

		$status = $response->getStatusCode();
		if((($status === 404) || Config::inst()->get('LinkMappingRequestFilter', 'replace_default')) && ($map = $this->service->getMappingByRequest($request))) {

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

		// Determine the fallback when using the CMS module.

		else if(($status === 404) && ($fallback = $this->service->determineFallback($request->getURL()))) {

			// Update the response code where appropriate.

			$responseCode = $fallback['code'];
			if($responseCode === 0) {
				$responseCode = 303;
			}

			// Update the response using the fallback, enforcing no further redirection.

			$response->redirect(HTTP::setGetVar('direct', true, Controller::join_links(Director::absoluteBaseURL(), $fallback['link'])), $responseCode);
		}

		// Continue processing the response.

		return true;
	}

}
