<?php
require_once 'TestRunner.php';
if(hasPhpUnit()) {
require_once 'PHPUnit/Framework.php';
}

/**
 * Test case class for the Sapphire framework.
 * Sapphire unit testing is based on PHPUnit, but provides a number of hooks into our data model that make it easier to work with.
 * 
 * @package sapphire
 * @subpackage testing
 */
class SapphireTest extends PHPUnit_Framework_TestCase {
	/**
	 * Path to fixture data for this test run.
	 * If passed as an array, multiple fixture files will be loaded.
	 * Please note that you won't be able to refer with "=>" notation
	 * between the fixtures, they act independent of each other.
	 * 
	 * @var string|array
	 */
	static $fixture_file = null;
	
	protected $originalMailer;
	protected $originalMemberPasswordValidator;
	protected $originalRequirements;
	protected $originalIsRunningTest;
	
	protected $mailer;
	
	protected static $is_running_test = false;
	
	public static function is_running_test() {
		return self::$is_running_test;
	}
	
	/**
	 * @var array $fixtures Array of {@link YamlFixture} instances
	 */
	protected $fixtures; 
	
	function setUp() {
		// Mark test as being run
		$this->originalIsRunningTest = self::$is_running_test;
		self::$is_running_test = true;
		
		// Remove password validation
		$this->originalMemberPasswordValidator = Member::password_validator();
		$this->originalRequirements = Requirements::backend();
		Member::set_password_validator(null);
		Cookie::set_report_errors(false);

		$className = get_class($this);
		$fixtureFile = eval("return {$className}::\$fixture_file;");
		
		// Set up fixture
		if($fixtureFile) {
			if(substr(DB::getConn()->currentDatabase(),0,5) != 'tmpdb') {
				//echo "Re-creating temp database... ";
				self::create_temp_db();
				//echo "done.\n";
			}

			// This code is a bit misplaced; we want some way of the whole session being reinitialised...
			Versioned::reading_stage(null);

			singleton('DataObject')->flushCache();

			$dbadmin = new DatabaseAdmin();
			$dbadmin->clearAllData();
			
			$fixtureFiles = (is_array($fixtureFile)) ? $fixtureFile : array($fixtureFile);
			
			foreach($fixtureFiles as $fixtureFilePath) {
				$fixture = new YamlFixture($fixtureFilePath);
				$fixture->saveIntoDatabase();
				$this->fixtures[] = $fixture;
			}
			
			
		}
		
		// Set up email
		$this->originalMailer = Email::mailer();
		$this->mailer = new TestMailer();
		Email::set_mailer($this->mailer);
		Email::send_all_emails_to(null);
	}
	
	/**
	 * Called once per test case ({@link SapphireTest} subclass).
	 * This is different to {@link setUp()}, which gets called once
	 * per method. Useful to initialize expensive operations which
	 * don't change state for any called method inside the test,
	 * e.g. dynamically adding an extension. See {@link tear_down_once()}
	 * for tearing down the state again.
	 */
	static function set_up_once() {	
	}
	
	/**
	 * Array
	 */
	protected $fixtureDictionary;
	
	
	/**
	 * Get the ID of an object from the fixture.
	 * @param $className The data class, as specified in your fixture file.  Parent classes won't work
	 * @param $identifier The identifier string, as provided in your fixture file
	 * @return int
	 */
	protected function idFromFixture($className, $identifier) {
		if(!$this->fixtures) {
			user_error("You've called \$this->objFromFixture() but you haven't specified static \$fixture_file.\n" . 
				"Ensure that static \"\$fixture_file = 'module/tests/fixturefile.yml';\" is specified in your " .get_class($this). " class.", E_USER_WARNING);
			return;
		}
		
		foreach($this->fixtures as $fixture) {
			$match = $fixture->idFromFixture($className, $identifier);
			if($match) return $match;
		}
		
		return false;
	}
	
	/**
	 * Return all of the IDs in the fixture of a particular class name.
	 * Will collate all IDs form all fixtures if multiple fixtures are provided.
	 * 
	 * @param string $className
	 * @return A map of fixture-identifier => object-id
	 */
	protected function allFixtureIDs($className) {
		if(!$this->fixtures) {
			user_error("You've called \$this->objFromFixture() but you haven't specified static \$fixture_file.\n" . 
			"Ensure that static \"\$fixture_file = 'module/tests/fixturefile.yml';\" is specified in your " .get_class($this). " class.", E_USER_WARNING);
			return;
		}
		
		$ids = array();
		foreach($this->fixtures as $fixture) {
			$ids += $fixture->allFixtureIDs($className);
		}
		
		return $ids;
	}

	/**
	 * Get an object from the fixture.
	 * @param $className The data class, as specified in your fixture file.  Parent classes won't work
	 * @param $identifier The identifier string, as provided in your fixture file
	 */
	protected function objFromFixture($className, $identifier) {
		if(!$this->fixtures) {
			user_error("You've called \$this->objFromFixture() but you haven't specified static \$fixture_file.\n" . 
			"Ensure that static \"\$fixture_file = 'module/tests/fixturefile.yml';\" is specified in your " .get_class($this). " class.", E_USER_WARNING);
			return;
		}
		
		foreach($this->fixtures as $fixture) {
			$match = $fixture->objFromFixture($className, $identifier);
			if($match) return $match;
		}
		
		return false;
	}
	
	/**
	 * Load a YAML fixture file into the database.
	 * Once loaded, you can use idFromFixture() and objFromFixture() to get items from the fixture.
	 * Doesn't clear existing fixtures.
	 *
	 * @param $fixtureFile The location of the .yml fixture file, relative to the site base dir
	 */
	function loadFixture($fixtureFile) {
		$parser = new Spyc();
		$fixtureContent = $parser->load(Director::baseFolder().'/'.$fixtureFile);
		
		$fixture = new YamlFixture($fixtureFile);
		$fixture->saveIntoDatabase();
		$this->fixtures[] = $fixture;
	}
	
	/**
	 * Clear all fixtures which were previously loaded through
	 * {@link loadFixture()}.
	 */
	function clearFixtures() {
		$this->fixtures = array();
	}
	
	function tearDown() {
		// Restore email configuration
		Email::set_mailer($this->originalMailer);
		$this->originalMailer = null;
		$this->mailer = null;

		// Restore password validation
		Member::set_password_validator($this->originalMemberPasswordValidator);
		
		// Restore requirements
		Requirements::set_backend($this->originalRequirements);

		// Mark test as no longer being run - we use originalIsRunningTest to allow for nested SapphireTest calls
		self::$is_running_test = $this->originalIsRunningTest;
		$this->originalIsRunningTest = null;
	}
	
	static function tear_down_once() {
	}
	
	/**
	 * Clear the log of emails sent
	 */
	function clearEmails() {
		return $this->mailer->clearEmails();
	}

	/**
	 * Search for an email that was sent.
	 * All of the parameters can either be a string, or, if they start with "/", a PREG-compatible regular expression.
	 * @param $to
	 * @param $from
	 * @param $subject
	 * @param $content
	 * @return An array containing the keys: 'type','to','from','subject','content', 'plainContent','attachedFiles','customHeaders','htmlContent',inlineImages'
	 */
	function findEmail($to, $from = null, $subject = null, $content = null) {
		return $this->mailer->findEmail($to, $from, $subject, $content);
	}
	
	/**
	 * Assert that the matching email was sent since the last call to clearEmails()
	 * All of the parameters can either be a string, or, if they start with "/", a PREG-compatible regular expression.
	 * @param $to
	 * @param $from
	 * @param $subject
	 * @param $content
	 * @return An array containing the keys: 'type','to','from','subject','content', 'plainContent','attachedFiles','customHeaders','htmlContent',inlineImages'
	 */
	function assertEmailSent($to, $from = null, $subject = null, $content = null) {
		// To do - this needs to be turned into a "real" PHPUnit ass
		if(!$this->findEmail($to, $from, $subject, $content)) {
			
			$infoParts = "";
			$withParts = array();
			if($to) $infoParts .= " to '$to'";
			if($from) $infoParts .= " from '$from'";
			if($subject) $withParts[] = "subject '$subject'";
			if($content) $withParts[] = "content '$content'";
			if($withParts) $infoParts .= " with " . implode(" and ", $withParts);
			
			throw new PHPUnit_Framework_AssertionFailedError(
                "Failed asserting that an email was sent$infoParts."
            );
		}
	}
	
	/**
	 * Returns true if we are currently using a temporary database
	 */
	static function using_temp_db() {
		$dbConn = DB::getConn();
		return $dbConn && (substr($dbConn->currentDatabase(),0,5) == 'tmpdb');
	}
	
	/**
	 * @todo Make this db agnostic
	 */
	static function kill_temp_db() {
		// Delete our temporary database
		if(self::using_temp_db()) {
			$dbConn = DB::getConn();
			$dbName = $dbConn->currentDatabase();
			if($dbName && DB::getConn()->databaseExists($dbName)) {
				// echo "Deleted temp database " . $dbConn->currentDatabase() . "\n";
				$dbConn->dropDatabase();

				// Todo: it would be good to remove this inappropriate coupling, somehow.
				// The versioned class keeps a static cache of information about temporary tables.
				Versioned::on_db_reset();
			}
		}
	}
	
	/**
	 * @todo Make this db agnostic
	 */
	static function create_temp_db() {
		// Create a temporary database
		$dbConn = DB::getConn();
		$dbname = 'tmpdb' . rand(1000000,9999999);
		while(!$dbname || $dbConn->databaseExists($dbname)) {
			$dbname = 'tmpdb' . rand(1000000,9999999);
		}
		
		$dbConn->selectDatabase($dbname);
		$dbConn->createDatabase();

		$dbadmin = new DatabaseAdmin();
		$dbadmin->doBuild(true, false, true);
		
		return $dbname;
	}
	
	static function delete_all_temp_dbs() {
		foreach(DB::getConn()->allDatabaseNames() as $dbName) {
			if(preg_match('/^tmpdb[0-9]+$/', $dbName)) {
				DB::getConn()->dropDatabaseByName($dbName);
				echo "<li>Dropped databse \"$dbName\"\n";
				flush();
			}
		}
	}
}

?>
