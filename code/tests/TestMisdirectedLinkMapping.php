<?php

/**
 * @author marcus
 */
class TestMisdirectedLinkMapping extends SapphireTest {
	
	public function testRegexUrlReplacement() {
		$mapping = new LinkMapping(array(
			'LinkType'		=> 'Regular Expression',
			'MappedLink'	=> '^case-study(.*)',
			'RedirectLink'	=> 'our-stories/case-studies?source=\\1',
		));
		
		$mapping->setMatchedURL('case-study-blood-stage-malaria-vaccine');
		
		$link = $mapping->getLink();
		
		$this->assertEquals('/our-stories/case-studies?source=-blood-stage-malaria-vaccine', $link);
		
	}
	
	public function testMappingToResult() {
		$mapping = new LinkMapping(array(
			'LinkType'		=> 'Regular Expression',
			'MappedLink'	=> '^case-study(.*)',
			'RedirectLink'	=> 'our-stories/case-studies?source=\\1',
		));
		$mapping->setMatchedURL('case-study-blood-stage-malaria-vaccine');
		
		$service = singleton('MisdirectionService');
		
		$out = $service->getRecursiveMapping($mapping, null, true);
		
		$this->assertEquals(1, count($out));
		$this->assertEquals('our-stories/case-studies?source=-blood-stage-malaria-vaccine', $out[0]['RedirectLink']);
	}
}
