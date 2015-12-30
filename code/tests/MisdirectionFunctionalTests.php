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

		$mapping = LinkMapping::create(
			array(
				'LinkType' => 'Simple',
				'MappedLink' => 'wrong/page',
				'RedirectLink' => 'pending'
			)
		);
		$mapping->write();
		LinkMapping::create(
			array(
				'LinkType' => 'Simple',
				'MappedLink' => 'pending',
				'RedirectLink' => 'correct/page'
			)
		)->write();

		// The CMS module needs to be present to test page behaviour.

		if(ClassInfo::exists('SiteTree')) {

			// This is required to support multiple sites.

			$this->logInAs(Member::default_admin());
			$parentID = ClassInfo::exists('Multisites') ? Multisites::inst()->getCurrentSiteId() : 0;

			// Instantiate pages to use.

			$first = SiteTree::create(
				array(
					'URLSegment' => 'wrong',
					'ParentID' => $parentID
				)
			);
			$first->writeToStage('Stage');
			$first->writeToStage('Live');
			$second = SiteTree::create(
				array(
					'URLSegment' => 'page',
					'ParentID' => $first->ID
				)
			);
			$second->writeToStage('Stage');
			$second->writeToStage('Live');
		}

		// Determine whether the enforce misdirection is functioning correctly.

		$response = $this->get('wrong/page');
		$this->assertEquals($response->getStatusCode(), 303);
		$this->assertEquals($response->getHeader('Location'), '/correct/page');

		// The CMS module needs to be present to test page behaviour.

		if(ClassInfo::exists('SiteTree')) {

			// Update the default enforce misdirection.

			Config::inst()->update('MisdirectionRequestFilter', 'enforce_misdirection', false);

			// Determine whether the page is now matched.

			$response = $this->get('wrong/page');
			$this->assertEquals($response->getStatusCode(), 200);
			$this->assertEquals($response->getHeader('Location'), null);

			// Instantiate a fallback to use.

			$second->deleteFromStage('Live');
			$second->deleteFromStage('Stage');
			$first->Fallback = 'Nearest';
			$first->writeToStage('Stage');
			$first->writeToStage('Live');

			// Determine whether the fallback is matched.

			$mapping->delete();
			$response = $this->get('wrong/page');
			$this->assertEquals($response->getStatusCode(), 303);
			$this->assertEquals($response->getHeader('Location'), '/wrong/?direct=1');
		}

		// Instantiate a director rule to use.

		Config::inst()->update('Director', 'rules', array(
			'wrong/page' => 'Controller'
		));

		// Determine whether the director rule is matched.

		$response = $this->get('wrong/page');
		$this->assertEquals($response->getStatusCode(), 200);
		$this->assertEquals($response->getHeader('Location'), null);

		// The database needs to be emptied to prevent further testing conflict.

		self::empty_temp_db();
	}

}
