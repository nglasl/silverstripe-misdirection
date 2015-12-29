<?php

/**
 *	The misdirection specific functional testing.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 *	@author Marcus Nyeholt <marcus@silverstripe.com.au>
 */

class MisdirectionFunctionalTests extends FunctionalTest {

	/**
	 *	The test to ensure the request filter is functioning correctly.
	 */

	public function testRequestFilter() {

		// Instantiate link mappings to use (the equivalent of does NOT include hostname).

		$first = LinkMapping::create(
			array(
				'LinkType' => 'Simple',
				'MappedLink' => 'wrong/page',
				'RedirectLink' => 'pending'
			)
		);
		$first->write();
		LinkMapping::create(
			array(
				'LinkType' => 'Simple',
				'MappedLink' => 'pending',
				'RedirectLink' => 'correct/page'
			)
		)->write();

		// Instantiate a request to use.

		$request = new SS_HTTPRequest('GET', 'wrong/page');

		// Determine whether the simple link mappings are functioning correctly.

		$testing = true;
		$service = singleton('MisdirectionService');
		$chain = $service->getMappingByRequest($request, $testing);
		$this->assertEquals(count($chain), 2);
		$match = end($chain);
		$this->assertEquals($match['LinkMapping']->getLink(), '/correct/page');

		// Update the link mapping and request (to the equivalent of includes hostname).

		$first->MappedLink = 'www.site.com/wrong/page';
		$first->IncludesHostname = 1;
		$first->write();
		$request->addHeader('Host', 'www.site.com');

		// Determine whether the simple link mappings are functioning correctly.

		$chain = $service->getMappingByRequest($request, $testing);
		$this->assertEquals(count($chain), 2);
		$match = end($chain);
		$this->assertEquals($match['LinkMapping']->getLink(), '/correct/page');

		// The database needs to be emptied to prevent further testing conflict.

		self::empty_temp_db();
	}

}
