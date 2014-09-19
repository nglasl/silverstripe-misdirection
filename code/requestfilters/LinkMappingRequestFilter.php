<?php

/**
 *	Handles the current director request/response and appropriately delegates the link mapping control.
 *
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class LinkMappingRequestFilter implements RequestFilter {

	public $service;

	private static $dependencies = array(
		'service' => '%$MisdirectionService',
	);

	private static $replace_default = true;
	private static $maximum_requests = 9;

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {

		return true;
	}

	/**
	 *	Attempt to redirect towards a link mapping.
	 */

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		// Allow customisation around whether the default SS automated redirect is replaced, where a page not found (404) will always attempt to trigger a link mapping.

		$status = $response->getStatusCode();
		if((Config::inst()->get('LinkMappingRequestFilter', 'replace_default') || ($status === 404)) && ($map = $this->service->getMappingByRequest($request))) {

			// Update the redirect response code appropriately.

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

			// Direct the response towards the final link mapping.

			$response->redirect($map->getLink(), $responseCode);
		}

		// Trigger any fallbacks so long as the CMS module exists.

		else if(($status === 404) && ClassInfo::exists('SiteTree')) {
			$link = trim($request->getURL(), '/');
			
			if (strlen($link)) {
				// break it up - we need at LEAST two segments for this to apply, because otherwise we don't have
				// the scenario of a missing child...
				$segments = explode('/', $link);
				
				$c = count($segments);
				if ($c > 0) {
					if ($option = $this->determineFallbackOption($segments)) {
						$response->redirect($option['link'], $option['code']);
					}
				}
			}
		}
		return true;
	}
	
	/**
	 * Determine the fallback path to take for a given set of URL segments
	 * 
	 * @param array $segments
	 */
	public function determineFallbackOption($segments) {
		
		$c = count($segments); 
		
		// go through url segments, find out a) the most recent rule set, and b) what the last found node is
		// we need to track the last known specific settings as they're inherited down the chain 
		$applicableRule = null;
		$specificUrl = null;
		$thisPage = null;
		$nearestParent = null;
		$parentId = 0;
		$responseCode = 301;
		
		// capture any site config specific settings. 
		$config = SiteConfig::current_site_config();
		if ($config && $config->FallbackRule) {
			$thisPage = $nearestParent =  Director::baseURL();
			$applicableRule = $config->FallbackRule;
			$specificUrl = $config->FallbackUrl;
			$responseCode = $config->FallbackResponse;
		}

		$applyRule = false;
		for ($i = 0; $i < $c; $i++) {
			$page = SiteTree::get()->filter(array(
				'URLSegment'	=> $segments[$i],
				'ParentID'		=> $parentId,
			))->first();

			if ($page) {
				$nearestParent = ($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link();
				if ($page->FallbackRule) {
					$parentId = $page->ID;
					$applicableRule = $page->FallbackRule;
					$thisPage = ($page->Link() === Director::baseURL()) ? Controller::join_links(Director::baseURL(), 'home/') : $page->Link();
					$responseCode = $page->FallbackResponse;
					// we might not actually use this, but we record it anyway in case
					$specificUrl = $page->FallbackUrl;
				}
			} else {
				$applyRule = true;
				break;
			}
		}

		if ($applyRule && strlen($applicableRule)) {
			$linkTo = null;
			switch ($applicableRule) {
				case 'URL': {
					$linkTo = $specificUrl;
					break;
				}
				case 'ThisPage': {
					$linkTo = $thisPage;
					break;
				}
				case 'Nearest': {
					$linkTo = $nearestParent;
					break;
				}
			}
			if (strlen($linkTo)) {
				return array('link' => $linkTo, 'code' => $responseCode ? $responseCode : 301);
			}
		}
	}

}
