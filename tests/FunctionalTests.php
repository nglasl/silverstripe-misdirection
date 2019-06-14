<?php

namespace nglasl\misdirection\tests;

use nglasl\misdirection\LinkMapping;
use nglasl\misdirection\MisDirectionRequestProcessor;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use Symbiote\Multisites\Multisites;

/**
 *	The misdirection specific functional testing.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class FunctionalTests extends FunctionalTest {

	protected $usesDatabase = true;

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

		if(ClassInfo::exists(SiteTree::class)) {

			// This is required to support multiple sites.

			$this->logInAs(Member::default_admin());
			$parentID = ClassInfo::exists(Multisites::class) ? Multisites::inst()->getCurrentSiteId() : 0;

			// Instantiate pages to use.

			$first = SiteTree::create(
				array(
					'URLSegment' => 'wrong',
					'ParentID' => $parentID
				)
			);
			$first->writeToStage('Stage');
			$first->publishRecursive();
			$second = SiteTree::create(
				array(
					'URLSegment' => 'page',
					'ParentID' => $first->ID
				)
			);
			$second->writeToStage('Stage');
			$second->publishRecursive();
		}

		// Determine whether the enforce misdirection is functioning correctly.

		$response = $this->get('wrong/page');
		$this->assertEquals($response->getStatusCode(), 301);
		$this->assertEquals($response->getHeader('Location'), '/correct/page');

		// The CMS module needs to be present to test page behaviour.

		if(ClassInfo::exists(SiteTree::class)) {

			// Update the default enforce misdirection.

			Config::modify()->set(MisDirectionRequestProcessor::class, 'enforce_misdirection', false);

			// Determine whether the page is now matched.

			$response = $this->get('wrong/page');
			$this->assertEquals($response->getStatusCode(), 200);
			$this->assertEquals($response->getHeader('Location'), null);

			// Instantiate a fallback to use.

			$first->Fallback = 'Nearest';
			$first->writeToStage('Stage');
			$first->publishRecursive();

			// The database needs to be cleaned up to prevent further testing conflict.

			$second->deleteFromStage('Live');
			$second->deleteFromStage('Stage');
			$mapping->delete();

			// Determine whether the fallback is matched.

			$response = $this->get('wrong/page');
			$this->assertEquals($response->getStatusCode(), 303);
			$this->assertEquals($response->getHeader('Location'), '/wrong/?misdirected=1');
		}
		else {

			// The database needs to be cleaned up to prevent further testing conflict.

			$mapping->delete();
		}

		// Instantiate a director rule to use.

		Config::modify()->set(Director::class, 'rules', array(
			'wrong/page' => Controller::class
		));

		// Determine whether the director rule is matched.

		$response = $this->get('wrong/page');
		$this->assertEquals($response->getStatusCode(), 200);
		$this->assertEquals($response->getHeader('Location'), null);
	}

}
