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

	private static $replace_default = true;

	private static $maximum_requests = 9;

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {

		return true;
	}

	/**
	 *	Attempt to redirect towards the highest priority link mapping that may have been defined.
	 */

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		// Either hook into a page not found or replace the default automated URL handling.

		$status = $response->getStatusCode();
		if((($status === 404) || Config::inst()->get('LinkMappingRequestFilter', 'replace_default')) && ($map = $this->service->getMappingByRequest($request))) {

			// Update the response code where appropriate.

			$responseCode = (int)$map->ResponseCode;
			if($responseCode === 0) {
				$responseCode = 303;
			}
			else if(($responseCode === 301) && $map->ForwardPOSTRequest) {
				$responseCode = 308;
			}
			else if(($responseCode === 303) && $map->ForwardPOSTRequest) {
				$responseCode = 307;
			}

			// Update the response using the link mapping redirection.

			$response->redirect($map->getLink(), $responseCode);
		}

		// Determine the fallback when using the CMS module.

		else if(($status === 404) && ($option = $this->service->determineFallback($request->getURL()))) {
			$response->redirect($option['link'], $option['code']);
		}
		return true;
	}

}
