<?php

class MisdirectionControllerExtension extends Extension {

	public function onBeforeHTTPError($statusCode, $request) {
		if ($statusCode == 404) 
		{
			// Get URL and remove the last URLSegment (ie. the current page)
			$dirParts = explode('/', $request->getURL(true));
			array_pop($dirParts);
			$fallbackURL = implode('/', $dirParts);
			// HACK(Jake): 'determineFallback' requires last URLSegment to not exist in SiteTree
			//			   (as of 2016-06-01)
			$fallbackURL .= '/ ';

			/**
			 * @var MisdirectionService
			 */
			$service = Injector::inst()->get('MisdirectionService');
			$fallback = $service->determineFallback($fallbackURL);
			if ($fallback)
			{
				$response = $this->owner->getResponse();
				$response->redirect($fallback['link'], 302);
				throw new SS_HTTPResponse_Exception($response);
			}
		}
	}
}
