<?php
/**
 * @package sapphire
 * @subpackage testing
 */
class TranslatableSearchFormTest extends FunctionalTest {
	
	static $fixture_file = 'sapphire/tests/search/TranslatableSearchFormTest.yml';
	
	protected $mockController;

	protected $requiredExtensions = array(
		'SiteTree' => array('Translatable'),
	);
	
	function setUp() {
		parent::setUp();
		
		$holderPage = $this->objFromFixture('SiteTree', 'searchformholder');
		$this->mockController = new ContentController($holderPage);
		
		// whenever a translation is created, canTranslate() is checked
		$admin = $this->objFromFixture('Member', 'admin');
		$admin->logIn();
	}
	
	
		
	function testPublishedPagesMatchedByTitleInDefaultLanguage() {
		$sf = new SearchForm($this->mockController, 'SearchForm');

		$publishedPage = $this->objFromFixture('SiteTree', 'publishedPage');
		$publishedPage->publish('Stage', 'Live');
		$translatedPublishedPage = $publishedPage->createTranslation('de_DE');
		$translatedPublishedPage->Title = 'translatedPublishedPage';
		$translatedPublishedPage->Content = 'German content';
		$translatedPublishedPage->write();
		$translatedPublishedPage->publish('Stage', 'Live');
		
		// Translatable::set_current_locale() can't be used because the context
		// from the holder is not present here - we set the language explicitly
		// through a pseudo GET variable in getResults()
		
		$lang = 'en_US';
		$results = $sf->getResults(null, array('Search'=>'content', 'locale'=>$lang));
		$this->assertContains(
			$publishedPage->ID,
			$results->column('ID'),
			'Published pages are found by searchform in default language'
		);
		$this->assertNotContains(
			$translatedPublishedPage->ID,
			$results->column('ID'),
			'Published pages in another language are not found when searching in default language'
		);
		
		$lang = 'de_DE';
		$results = $sf->getResults(null, array('Search'=>'content', 'locale'=>$lang));
		$this->assertNotContains(
			$publishedPage->ID,
			$results->column('ID'),
			'Published pages in default language are not found when searching in another language'
		);
		$this->assertContains(
			(string)$translatedPublishedPage->ID,
			$results->column('ID'),
			'Published pages in another language are found when searching in this language'
		);
	}

}
?>