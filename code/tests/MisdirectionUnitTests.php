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

		// Update the first link mapping and request (to the equivalent of includes hostname).

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

		$mapping->MappedLink = '^www\.wrong\.com(/page)?/(index){1}\.php$';
		$mapping->RedirectLink = 'https://www.correct.com$1';
		$mapping->setMatchedURL('www.wrong.com/page/index.php');

		// Determine whether the regular expression replacement is correct.

		$this->assertEquals($mapping->getLink(), 'https://www.correct.com/page');
	}

	/**
	 *	The test to ensure regular expression link mappings are functioning correctly.
	 */

	public function testRegularExpressionLinkMappings() {

		// Instantiate link mappings to use (the equivalent of does NOT include hostname).

		$first = LinkMapping::create(
			array(
				'LinkType' => 'Regular Expression',
				'MappedLink' => '^wrong(.*)$',
				'RedirectLink' => 'pending\\1'
			)
		);
		$first->write();
		LinkMapping::create(
			array(
				'LinkType' => 'Regular Expression',
				'MappedLink' => '^pending(.*)$',
				'RedirectLink' => 'correct\\1'
			)
		)->write();

		// Instantiate a request to use.

		$request = new SS_HTTPRequest('GET', 'wrong/page');

		// Determine whether the regular expression link mappings are functioning correctly.

		$testing = true;
		$service = singleton('MisdirectionService');
		$chain = $service->getMappingByRequest($request, $testing);
		$this->assertEquals(count($chain), 2);
		$match = end($chain);
		$this->assertEquals($match['LinkMapping']->getLink(), '/correct/page');

		// Update the first link mapping and request (to the equivalent of includes hostname).

		$first->MappedLink = '^www\.site\.com/wrong(.*)$';
		$first->IncludesHostname = 1;
		$first->write();
		$request->addHeader('Host', 'www.site.com');

		// Determine whether the regular expression link mappings are functioning correctly.

		$chain = $service->getMappingByRequest($request, $testing);
		$this->assertEquals(count($chain), 2);
		$match = end($chain);
		$this->assertEquals($match['LinkMapping']->getLink(), '/correct/page');

		// The database needs to be emptied to prevent further testing conflict.

		self::empty_temp_db();
	}

}
