<?php

/**
 *	The misdirection specific unit tests, and functional tests.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 *	@author Marcus Nyeholt <marcus@silverstripe.com.au>
 */

class MisdirectionTests extends SapphireTest {

	/**
	 *	The test to ensure regular expression replacement is correct.
	 */

	public function testRegularExpressionReplacement() {

		// Instantiate a link mapping to use.

		$mapping = LinkMapping::create(array(
			'LinkType' => 'Regular Expression',
			'MappedLink' => '^wrong(.*)',
			'RedirectLink' => 'correct\\1'
		));
		$mapping->setMatchedURL('wrong/page');

		// Determine whether the regular expression replacement is correct.

		$this->assertEquals('/correct/page', $mapping->getLink());
		$testing = true;
		$mappings = singleton('MisdirectionService')->getRecursiveMapping($mapping, null, $testing);
		$this->assertEquals(1, count($mappings));
		$this->assertEquals('correct/page', $mappings[0]['RedirectLink']);
	}

}
