<?php

/**
 *	The misdirection specific unit tests.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 *	@author Marcus Nyeholt <marcus@silverstripe.com.au>
 */

class MisdirectionUnitTests extends SapphireTest {

	/**
	 *	The test to ensure regular expression replacement is correct.
	 */

	public function testRegularExpressionReplacement() {

		// Instantiate a link mapping to use.

		$mapping = LinkMapping::create(
			array(
				'LinkType' => 'Regular Expression',
				'MappedLink' => '^wrong(.*)',
				'RedirectLink' => 'correct\\1'
			)
		);
		$mapping->setMatchedURL('wrong/page');

		// Determine whether the regular expression replacement is correct.

		$this->assertEquals('/correct/page', $mapping->getLink());
	}

}
