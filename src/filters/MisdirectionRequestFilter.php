<?php

namespace nglasl\misdirection;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestFilter;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\ErrorPage\ErrorPage;

/**
 *	Hooks into the current director response and appropriately redirects towards the highest priority link mapping that may have been defined.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class MisdirectionRequestFilter implements RequestFilter {

	public $service;

	private static $dependencies = array(
		'service' => '%$' . MisdirectionService::class
	);

	/**
	 *	The status codes for redirection, since the core definitions are protected.
	 */

	private static $status_codes = array(
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect'
	);

	/**
	 *	The configuration for the default automated URL handling.
	 */

	private static $enforce_misdirection = true;

	private static $replace_default = false;

	/**
	 *	The maximum number of consecutive link mappings.
	 */

	private static $maximum_requests = 9;

	public function preRequest(HTTPRequest $request) {

		return true;
	}

	/**
	 *	Attempt to redirect towards the highest priority link mapping that may have been defined.
	 *
	 *	@URLparameter misdirected <{BYPASS_LINK_MAPPINGS}> boolean
	 */

	public function postRequest(HTTPRequest $request, HTTPResponse $response) {

		// Bypass the request filter when requesting specific director rules such as "/admin".

		$configuration = Config::inst();
		$requestURL = $request->getURL();
		$bypass = array(
			'admin',
			'Security',
			'CMSSecurity',
			'dev'
		);
		foreach($configuration->get(Director::class, 'rules') as $segment => $controller) {

			// Retrieve the specific director rules.

			if(($position = strpos($segment, '$')) !== false) {
				$segment = rtrim(substr($segment, 0, $position), '/');
			}

			// Determine if the current request matches a specific director rule.

			if($segment && in_array($segment, $bypass) && (($requestURL === $segment) || (strpos($requestURL, "{$segment}/") === 0))) {

				// Continue processing the response.

				return true;
			}
		}

		// Bypass the request filter when using the misdirected GET parameter.

		if($request->getVar('misdirected') || $request->getVar('direct')) {

			// Continue processing the response.

			return true;
		}

		// Determine the default automated URL handling response status.

		$status = $response->getStatusCode();
		$success = (($status >= 200) && ($status < 300));
		$error = ($status === 404);

		// Determine whether we're either hooking into a page not found or replacing the default automated URL handling.

		$enforce = $configuration->get(MisdirectionRequestFilter::class, 'enforce_misdirection');
		$replace = $configuration->get(MisdirectionRequestFilter::class, 'replace_default');
		if(($error || $enforce || $replace) && ($map = $this->service->getMappingByRequest($request))) {

			// Update the response code where appropriate.

			$responseCode = $map->ResponseCode;
			if($responseCode == 0) {
				$responseCode = 301;
			}

			// Determine the home page URL when replacing the default automated URL handling.

			$link = $map->getLink();
			$base = Director::baseURL();
			if($replace && (substr($link, 0, strlen($base)) === $base) && (substr($link, strlen($base)) === 'home/')) {
				$link = $base;
			}

			// Update the response using the link mapping redirection.

			$response->setBody('');
			$response->redirect($link, $responseCode);
		}

		// Determine a page not found fallback, when the CMS module is present.

		else if($error && ($fallback = $this->service->determineFallback($requestURL))) {

			// Update the response code where appropriate.

			$responseCode = $fallback['code'];
			if($responseCode === 0) {
				$responseCode = 303;
			}

			// Update the response using the fallback, enforcing no further redirection.

			$response->setBody('');
			$response->redirect($fallback['link'], $responseCode);
		}

		// When enabled, replace the default automated URL handling with a page not found.

		else if(!$error && !$success && $replace) {
			$response->setStatusCode(404);

			// Retrieve the appropriate page not found response.

			(ClassInfo::exists(SiteTree::class) && ($page = ErrorPage::response_for(404))) ? $response->setBody($page->getBody()) : $response->setBody('No URL was matched!');
		}

		// Continue processing the response.

		return true;
	}

}
