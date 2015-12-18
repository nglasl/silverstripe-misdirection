<?php

/**
 *	The misdirection specific unit testing.
 *	@author Nathan Glasl <nathan@silverstripe.com.au>
 *	@author Marcus Nyeholt <marcus@silverstripe.com.au>
 */

class MisdirectionUnitTests extends SapphireTest {

	/**
	 *	The test to ensure simple link mappings are functioning correctly.
	 */

	public function testSimpleLinkMappings() {

		// Instantiate link mappings to use (the equivalent of does NOT include hostname).

		$mapping = LinkMapping::create(
			array(
				'LinkType' => 'Simple',
				'MappedLink' => 'wrong',
				'RedirectLink' => 'pending'
			)
		);
		$mapping->write();
		LinkMapping::create(
			array(
				'LinkType' => 'Simple',
				'MappedLink' => 'pending',
				'RedirectLink' => 'correct'
			)
		)->write();

		// Instantiate a request to use.

		$request = new SS_HTTPRequest('GET', 'wrong');

		// Determine whether the simple link mappings are functioning correctly.

		$testing = true;
		$service = singleton('MisdirectionService');
		$chain = $service->getMappingByRequest($request, $testing);
		$this->assertEquals(count($chain), 2);
		$match = end($chain);
		$this->assertEquals(LinkMapping::get()->byID($match['ID'])->getLink(), '/correct');

		// Update the link mappings (to the equivalent of includes hostname).

		$mapping->MappedLink = 'www.wrong.com/wrong';
		$mapping->IncludesHostname = 1;
		$mapping->write();
		$request->setUrl('wrong');
		$request->addHeader('Host', 'www.wrong.com');

		// Determine whether the simple link mappings are functioning correctly.

		$chain = $service->getMappingByRequest($request, $testing);
		$this->assertEquals(count($chain), 2);
		$match = end($chain);
		$this->assertEquals(LinkMapping::get()->byID($match['ID'])->getLink(), '/correct');
	}

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

		$this->assertEquals($mapping->getLink(), '/correct/page');

		// Update the link mapping (to the equivalent of includes hostname).

		$mapping->MappedLink = '^www\.wrong\.com/(wrong/)?(index){1}\.php$';
		$mapping->RedirectLink = 'https://www.correct.com/correct/$2';
		$mapping->setMatchedURL('www.wrong.com/wrong/index.php');

		// Determine whether the regular expression replacement is correct.

		$this->assertEquals($mapping->getLink(), 'https://www.correct.com/correct/index');
	}

}
