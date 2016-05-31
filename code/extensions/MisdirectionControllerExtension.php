<?php

class MisdirectionControllerExtension extends Extension {

	public function onBeforeHTTPError($statusCode, $request) {
		if ($statusCode == 404) 
		{
			$service = Injector::inst()->get('MisdirectionService');
			$fallback = $service->determineFallback($request->getURL(true));
			if ($fallback)
			{
				$response = $this->owner->getResponse();
				$response->redirect($fallback['link'], 302);
				throw new SS_HTTPResponse_Exception($response);
			}
		}
	}
}
