<?php

/**
 *	The misdirection specific functional testing.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

class MisdirectionFunctionalTests extends FunctionalTest {

	/**
	 *	This is to prevent following the request filter's redirect.
	 */

	protected $autoFollowRedirection = false;

	/**
	 *	The test to ensure the request filter is functioning correctly.
	 */

	public function testRequestFilter() {

		// Instantiate link mappings to use.

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

		// Determine whether the request filter is functioning correctly.

		$response = $this->get('wrong/page');
		$this->assertEquals($response->getStatusCode(), 303);
		$this->assertEquals($response->getHeader('Location'), '/correct/page');

		// The database needs to be emptied to prevent further testing conflict.

		self::empty_temp_db();
	}

}
