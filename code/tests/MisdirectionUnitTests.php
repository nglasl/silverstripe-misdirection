<?php

/**
 *	The misdirection specific unit testing.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 *	@author Marcus Nyeholt <marcus@silverstripe.com.au>
 */

class MisdirectionUnitTests extends SapphireTest {

	/**
	 *	The test to ensure regular expression replacement is correct.
	 */

	public function testRegularExpressionReplacement() {

		// Instantiate a link mapping to use (the equivalent of does NOT include hostname).

		$mapping = LinkMapping::create(
			array(
				'LinkType' => 'Regular Expression',
				'MappedLink' => '^wrong(.*)$',
				'RedirectLink' => 'correct\\1'
			)
		);
		$mapping->setMatchedURL('wrong/page');

		// Determine whether the regular expression replacement is correct.

		$this->assertEquals('/correct/page', $mapping->getLink());

		// Update the link mapping (to the equivalent of includes hostname).

		$mapping->MappedLink = '^www\.wrong\.com/(page/)?(index){1}\.php$';
		$mapping->RedirectLink = 'https://www.correct.com/page/$2';
		$mapping->setMatchedURL('www.wrong.com/page/index.php');

		// Determine whether the regular expression replacement is correct.

		$this->assertEquals('https://www.correct.com/page/index', $mapping->getLink());
	}

}
