<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 6/14/19
 * Time: 10:23 AM
 * To change this template use File | Settings | File Templates.
 */

namespace nglasl\misdirection;


use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class MisDirectionRequestProcessor implements HTTPMiddleware
{

	use Configurable;
	use Injectable;

	private static $status_codes = array(
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect'
	);

	public $service;

	private static $dependencies = array(
		'service' => '%$' . MisdirectionService::class
	);

	private static $enforce_misdirection = true;

	private static $replace_default = false;

	/**
	 *	The maximum number of consecutive link mappings.
	 */
	private static $maximum_requests = 9;

	public function process(HTTPRequest $request, callable $delegate)
	{
		/* @var $response HTTPResponse */
		$response = $delegate($request);
		$requestURL = $request->getURL();
		$bypass = array(
			'admin',
			'Security',
			'CMSSecurity',
			'dev'
		);

		foreach(Director::config()->get('rules') as $segment => $controller) {

			// Retrieve the specific director rules.

			if(($position = strpos($segment, '$')) !== false) {
				$segment = rtrim(substr($segment, 0, $position), '/');
			}

			// Determine if the current request matches a specific director rule.

			if($segment && in_array($segment, $bypass) && (($requestURL === $segment) || (strpos($requestURL, "{$segment}/") === 0))) {

				// Continue processing the response.
				return $response;
			}

			if($request->getVar('misdirected') || $request->getVar('direct')) {

				// Continue processing the response.
				return $response;
			}
		}

		if($response) {

			$status = $response ? $response->getStatusCode() : null;
			$success = (($status >= 200) && ($status < 300));
			$error = ($status === 404);

			$enforce = $this->config()->get('enforce_misdirection');
			$replace = $this->config()->get('replace_default');

			if(($error || $enforce || $replace) && ($map = $this->service->getMappingByRequest($request))) {

				$responseCode = $map->ResponseCode;
				if($responseCode == 0) {
					$responseCode = 301;
				}

				$link = $map->getLink();
				$base = Director::baseURL();
				if($replace && (substr($link, 0, strlen($base)) === $base) && (substr($link, strlen($base)) === 'home/')) {
					$link = $base;
				}

				// Update the response using the link mapping redirection.

				$response->setBody('');
				$response->redirect($link, $responseCode);
			}
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

		}
		return $response;

	}

}