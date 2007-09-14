<?php
class Member extends DataObject {

	static $db = array(
		'FirstName' => "Varchar",
		'Surname' => "Varchar",
		'Email' => "Varchar",
		'Password' => "Varchar",
		'RememberLoginToken' => "Varchar(50)",
		'NumVisit' => "Int",
		'LastVisited' => 'Datetime',
		'Bounced' => 'Boolean',
		'AutoLoginHash' => 'Varchar(10)',
		'AutoLoginExpired' => 'Datetime',
		'BlacklistedEmail' => 'Boolean',
	);
	static $belongs_many_many = array(
		"Groups" => "Group",

	);

	static $has_many = array(
		'UnsubscribedRecords' => 'Member_UnsubscribeRecord'
	);

	static $default_sort = "Surname, FirstName";

	static $indexes = array(
		'Email' => true,
	);


	/**
	 * Logs this member in
	 *
	 * @param bool $remember If set to TRUE, the member will be logged in
	 *                       automatically the next time.
	 */
	function logIn($remember = false) {
		session_regenerate_id(true);
		Session::set("loggedInAs", $this->ID);

		$this->NumVisit++;

		if($remember) {

			$token = substr(md5(uniqid(rand(), true)), 0, 49 - strlen($this->ID));
			$this->RememberLoginToken = $token;
			Cookie::set('alc_enc', $this->ID . ':' . $token);
		} else {
			$this->RememberLoginToken = null;
			Cookie::set('alc_enc', null);
			Cookie::forceExpiry('alc_enc');
		}

		$this->write();
	}


	/**
	 * Log the user in if the "remember login" cookie is set
	 *
	 * The <i>remember login token</i> will be changed on every successful
	 * auto-login.
	 */
	static function autoLogin() {
		if(isset($_COOKIE['alc_enc']) && !Session::get("loggedInAs")) {

			list($uid, $token) = explode(':', $_COOKIE['alc_enc'], 2);
			$uid = Convert::raw2sql($uid);
			$token = Convert::raw2sql($token);

			$member = DataObject::get_one(
					"Member", "Member.ID = '$uid' And RememberLoginToken = '$token'");

			if($member) {
				session_regenerate_id(true);
				Session::set("loggedInAs", $member->ID);

				$token = substr(md5(uniqid(rand(), true)),
				                0, 49 - strlen($member->ID));
				$member->RememberLoginToken = $token;
				Cookie::set('alc_enc', $member->ID . ':' . $token);

				$member->NumVisit++;
				$member->write();
			}
		}
	}


	/**
	 * Logs this member out.
	 */
	function logOut() {
		Session::clear("loggedInAs");
		session_regenerate_id(true);

		$this->RememberLoginToken = null;
		Cookie::set('alc_enc', null);
		Cookie::forceExpiry('alc_enc');

		$this->write();
	}


	/**
	 * Generate an auto login hash
	 *
	 * @todo This is relative insecure, check if we should fix it
	 */
	function generateAutologinHash() {
		$linkHash = sprintf('%10d', time() );

		while( DataObject::get_one( 'Member', "`AutoLoginHash`='$linkHash'" ) )
			$linkHash = sprintf('%10d', abs( time() * rand( 1, 10 ) ) );

		$this->AutoLoginHash = $linkHash;
		$this->AutoLoginExpired = date('Y-m-d', time() + ( 60 * 60 * 24 * 14 ) );

		$this->write();
	}


	/**
	 * Log a member in with an auto login hash link
	 */
	static function autoLoginHash($RAW_hash) {
		$SQL_hash = Convert::raw2sql($RAW_hash);

		$member = DataObject::get_one('Member',"`AutoLoginHash`='$SQL_hash' AND `AutoLoginExpired` > NOW()");

		if($member)
			$member->logIn();

		return $member;
	}


	/**
	 * Send signup, change password or forgot password informations to an user
	 *
	 * @param string $type Information type to send ("signup",
	 *                     "changePassword" or "forgotPassword")
	 */
	function sendInfo($type = 'signup') {
		switch($type) {
			case "signup":
				$e = new Member_SignupEmail();
				break;
			case "changePassword":
				$e = new Member_ChangePasswordEmail();
				break;
			case "forgotPassword":
				$e = new Member_ForgotPasswordEmail();
				break;
		}
		$e->populateTemplate($this);
		$e->send();
	}


	/**
	 * Returns the fields for the member form
	 *
	 * @return FieldSet Returns a {@link FieldSet} containing the fields for
	 *                  the member form.
	 */
	function getMemberFormFields() {
		return new FieldSet(
			new TextField("FirstName", "First Name"),
			new TextField("Surname", "Surname"),
			new TextField("Email", "Email"),
			new TextField("Password", "Password")
		);
	}


	/**
	 * Factory method for the member validator
	 *
	 * @return Member_Validator Returns an instance of a
	 *                          {@link Member_Validator} object.
	 */
	function getValidator() {
		return new Member_Validator();
	}


	/**
	 * Returns the current logged in user
	 *
	 * @return bool|Member Returns the member object of the current logged in
	 *                     user or FALSE.
	 */
	static function currentUser() {
		$id = Session::get("loggedInAs");
		if(!$id) {
			self::autoLogin();
			$id = Session::get("loggedInAs");
		}

		if($id) {
			return DataObject::get_one("Member", "Member.ID = $id");
		}
	}


	/**
	 * Get the ID of the current logged in user
	 *
	 * @return int Returns the ID of the current logged in user or 0.
	 */
	static function currentUserID() {
		$id = Session::get("loggedInAs");
		if(!$id) {
			self::autoLogin();
			$id = Session::get("loggedInAs");
		}

		return is_numeric($id) ? $id : 0;
	}


	/**
	 * Add the members email address to the blacklist
	 *
	 * With this method the blacklisted email table is updated to ensure that
	 * no promotional material is sent to the member (newsletters).
	 * Standard system messages are still sent such as receipts.
	 *
	 * @param bool $val Set to TRUE if the address should be added to the
	 *                  blacklist, otherwise to FALSE.
	 * @return
	 * @todo Check for what the parameter $val is needed! (Markus)
	 */
	function setBlacklistedEmail($val) {
		if($val && $this->Email) {
			$blacklisting = new Email_BlackList();
	 		$blacklisting->BlockedEmail = $this->Email;
	 		$blacklisting->MemberID = $this->ID;
	 		$blacklisting->write();
		}

		$this->setField("BlacklistedEmail", $val);
	}


	/*
	 * Generate a random password
	 *
	 * BDC - added randomiser to kick in if there's no words file on the
	 * filesystem.
	 *
	 * @return string Returns a random password.
	 */
	static function createNewPassword() {
		if(file_exists('/usr/share/silverstripe/wordlist.txt')) {
			$words = file('/usr/share/silverstripe/wordlist.txt');

			list($usec, $sec) = explode(' ', microtime());
			srand($sec + ((float) $usec * 100000));

			$word = trim($words[rand(0,sizeof($words)-1)]);
			$number = rand(10,999);

			return $word . $number;
		} else {
	    	$random = rand();
		    $string = md5($random);
    		$output = substr($string, 0, 6);
	    	return $output;
		}
	}


	/**
	 * Event handler called before writing to the database
	 *
	 * If an email's filled out look for a record with the same email and if
	 * found update this record to merge with that member.
	 */
	function onBeforeWrite() {
		if($this->Email) {
			if($this->ID) {
				$idClause = "AND `Member`.ID <> $this->ID";
			} else {
				$idClause = "";
			}

			$existingRecord = DataObject::get_one(
				"Member", "Email = '" . addslashes($this->Email) . "' $idClause");

			// Debug::message("Found an existing member for email $this->Email");

			if($existingRecord) {
				$newID = $existingRecord->ID;
				if($this->ID) {
					DB::query("UPDATE Group_Members SET MemberID = $newID WHERE MemberID = $this->ID");
				}
				$this->ID = $newID;
				// Merge existing data into the local record

				foreach($existingRecord->getAllFields() as $k => $v) {
					if(!isset($this->changed[$k]) || !$this->changed[$k]) $this->record[$k] = $v;
				}
			}
		}

		parent::onBeforeWrite();
	}


	/**
	 * Check if the member is in one of the given groups
	 *
	 * @param array $groups Groups to check
	 * @return bool Returns TRUE if the member is in one of the given groups,
	 *              otherwise FALSE.
	 */
	public function inGroups(array $groups) {
		foreach($this->Groups() as $group)
			$memberGroups[] = $group->Title;

		return count(array_intersect($memberGroups, $groups)) > 0;
	}


	/**
	 * Check if the member is in the given group
	 *
	 * @param int $groupID ID of the group to check
	 * @return bool Returns TRUE if the member is in the given group,
	 *              otherwise FALSE.
	 */
	public function inGroup($groupID) {
		foreach($this->Groups() as $group) {
			if($groupID == $group->ID)
				return true;
		}

		return false;
	}


	/**
	 * Alias for {@link inGroup}
	 *
	 * @param int $groupID ID of the group to check
	 * @return bool Returns TRUE if the member is in the given group,
	 *              otherwise FALSE.
	 * @see inGroup()
	 */
	public function isInGroup($groupID) {
    return $this->inGroup($groupID);
	}


	/**
	 * Returns true if this user is an administrator.
	 * Administrators have access to everything.  The lucky bastards! ;-)
	 * 
	 * @return Returns TRUE if this user is an administrator.
	 * @todo Should this function really exists? Is not {@link isAdmin()} the
	 *       only right name for this?
	 * @todo Is {@link Group}::CanCMSAdmin not deprecated?
	 */
	function _isAdmin() {
		if($groups = $this->Groups()) {
			foreach($groups as $group) {
				if($group->CanCMSAdmin)
					return true;
			}
		}

		return Permission::check('ADMIN');
	}


	/**
	 * Check if the user is an administrator
	 *
	 * Alias for {@link _isAdmin()} because the method is used in both ways
	 * all over the framework.
	 *
	 * @return Returns TRUE if this user is an administrator.
	 * @see _isAdmin()
	 */
	public function isAdmin() {
		return $this->_isAdmin();
	}
	function _isCMSUser() {
		if($groups = $this->Groups()) {
			foreach($groups as $group) {
				if($group->CanCMS)
					return true;
			}
		}
	}


	//------------------- HELPER METHODS -----------------------------------//

	/**
	 * Get the complete name of the member
	 *
	 * @return string Returns the first- and surname of the member. If the ID
	 *                of the member is equal 0, only the surname is returned.
	 * @todo Check for what this method is used! (Markus)
	 */
	public function getTitle() {
		if($this->getField('ID') === 0)
			return $this->getField('Surname');
		return $this->getField('Surname') . ', ' . $this->getField('FirstName');
	}


	/**
	 * Get the complete name of the member
	 *
	 * @return string Returns the first- and surname of the member.
	 */
	public function getName() {
		return $this->FirstName . ' ' . $this->Surname;
	}


	/**
	 * Set first- and surname
	 *
	 * This method assumes that the last part of the name is the surname, e.g.
	 * <i>A B C</i> will result in firstname <i>A B</i> and surname <i>C</i>
	 *
	 * @param string $name The name
	 */
	public function setName($name) {
		$nameParts = explode(' ', $name);
		$this->Surname = array_pop($nameParts);
		$this->FirstName = join(' ', $nameParts);
	}


	/**
	 * Alias for {@link setName}
	 *
	 * @param string $name The name
	 * @see setName()
	 */
	public function splitName($name) {
		return $this->setName($name);
	}

	//---------------------------------------------------------------------//


	/**
	 * @todo Figure out what this function does and document it! (Markus)
	 */
	public function Groups() {
		$groups = $this->getManyManyComponents("Groups");
		
		$unsecure = DataObject::get("Group_Unsecure", "");
		if($unsecure) {
			foreach($unsecure as $unsecureItem) {
				$groups->push($unsecureItem);
			}
		}

		$groupIDs = $groups->column();
		$collatedGroups = array();
		foreach($groups as $group) {
			$collatedGroups = array_merge((array)$collatedGroups, $group->collateAncestorIDs());
		}

		$table = "Group_Members";

		if(count($collatedGroups) > 0) {
			$collatedGroups = implode(", ", array_unique($collatedGroups));

			$result = singleton('Group')->instance_get("`ID` IN ($collatedGroups)", "ID", "", "", "Member_GroupSet");
		} else {
			$result = new Member_GroupSet();
		}

		$result->setComponentInfo("many-to-many", $this, "Member", $table, "Group");

		return $result;
	}


	/**
	 * @todo Figure out what this function does and document it! (Markus)
	 */
	public function map($filter = "", $sort = "", $blank="") {
		$ret = new SQLMap(singleton('Member')->extendedSQL($filter, $sort));
		if($blank) {
			$blankMember = new Member();
			$blankMember->Surname = $blank;
			$blankMember->ID = 0;

			$ret->getItems()->shift($blankMember);
		}
		return $ret;
	}


	/**
	 * @todo Figure out what this function does and document it! (Markus)
	 */
	public static function mapInGroups($groups = null) {
		if(!$groups)
			return Member::map();

		$groupIDList = array();

		if(is_a($groups, 'DataObjectSet')) {
			foreach( $groups as $group )
				$groupIDList[] = $group->ID;
		} elseif(is_array($groups)) {
			$groupIDList = $groups;
		} else {
			$groupIDList[] = $groups;
		}

		if(empty($groupIDList))
			return Member::map();

		return new SQLMap(singleton('Member')->extendedSQL(
			"`GroupID` IN (" . implode( ',', $groupIDList ) .
			")", "Surname, FirstName", "", "INNER JOIN `Group_Members` ON `MemberID`=`Member`.`ID`"));
	}


	/**
	 * Get a map of all members in the groups given that have CMS permissions
	 *
	 * If no groups are passed, all groups with CMS permissions will be used.
	 *
	 * @param array $groups Groups to consider or NULL to use all groups with
	 *                      CMS permissions.
	 * @return SQLMap Returns a map of all members in the groups given that
	 *                have CMS permissions.
	 */
	public static function mapInCMSGroups($groups = null) {
		if(!$groups || $groups->Count() == 0) {
			$groups = DataObject::get('Group', "", "",
				"INNER JOIN `Permission` ON `Permission`.GroupID = `Group`.ID AND `Permission`.Code IN ('ADMIN', 'CMS_ACCESS_AssetAdmin')");
		}

		$groupIDList = array();

		if(is_a($groups, 'DataObjectSet')) {
			foreach($groups as $group)
				$groupIDList[] = $group->ID;
		} elseif(is_array($groups)) {
			$groupIDList = $groups;
		}

		/*if( empty( $groupIDList ) )
			return Member::map();	*/

		$filterClause = ($groupIDList)
			? "`GroupID` IN (" . implode( ',', $groupIDList ) . ")"
			: "";

		return new SQLMap(singleton('Member')->extendedSQL($filterClause,
			"Surname, FirstName", "",
			"INNER JOIN `Group_Members` ON `MemberID`=`Member`.`ID` INNER JOIN `Group` ON `Group`.`ID`=`GroupID`"));
	}


	/**
	 * Get the groups in which the member is NOT in
	 *
	 * When passed an array of groups, and a component set of groups, this
	 * function will return the array of groups the member is NOT in.
	 *
	 * @param array $groupList An array of group code names.
	 * @param array $memberGroups A component set of groups (if set to NULL,
	 * 														$this->groups() will be used)
	 * @return array Groups in which the member is NOT in.
	 */
	public function memberNotInGroups($groupList, $memberGroups = null){
		if(!$memberGroups)
			$memberGroups = $this->Groups();

		foreach($memberGroups as $group) {
			if(in_array($group->Code, $groupList)) {
				$index = array_search($group->Code, $groupList);
				unset($groupList[$index]);
			}
		}
		return $groupList;
	}


	/**
	 * Return a {@link FieldSet} of fields that would appropriate for editing
	 * this member.
	 *
	 * @return FieldSet Return a FieldSet of fields that would appropriate for
	 *                  editing this member.
	 */
	public function getCMSFields() {
		$fields = new FieldSet(
				//new TextField("Salutation", "Title"),
				new HeaderField( "Personal Details" ),
				new TextField("FirstName", "First Name"),
				new TextField("Surname", "Surname"),
				new HeaderField( "User Details" ),
				new TextField("Email", "Email"),
				/*new TextField("Password", "Password")*/
				new PasswordField("Password", "Password")
				//new TextareaField("Address","Address"),
				//new TextField("JobTitle", "Job Title"),
				//new TextField( "Organisation", "Organisation" ),
				//new OptionsetField("HTMLEmail","Mail Format", array( 1 => 'HTML', 0 => 'Text only' ) )
			);
			
		$this->extend('updateCMSFields', $fields);
		// if($this->hasMethod('updateCMSFields')) $this->updateCMSFields($fields);
		
		return $fields;
	}


	/**
	 * Unsubscribe from newsletter
	 *
	 * @param NewsletterType $newsletterType Newsletter type to unsubscribe
	 *                                       from
	 */
	function unsubscribeFromNewsletter(NewsletterType $newsletterType) {
		// record today's date in unsubscriptions
		// this is a little bit redundant
		$unsubscribeRecord = new Member_UnsubscribeRecord();
		$unsubscribeRecord->unsubscribe($this, $newsletterType);
		$this->Groups()->remove($newsletterType->GroupID);
	}

	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		
		if(!DB::query("SELECT * FROM Member")->value() && isset($_REQUEST['username']) && isset($_REQUEST['password'])) {
			Security::findAnAdministrator($_REQUEST['username'], $_REQUEST['password']);
			Database::alteration_message("Added admin account","created");
		}
	}
}




/**
 * Special kind of {@link ComponentSet} that has special methods for
 * manipulating a user's membership
 */
class Member_GroupSet extends ComponentSet {
	/**
	 * Control group membership with a number of checkboxes.
	 *  - If the checkbox fields are present in $data, then the member will be
	 *    added to the group with the same codename.
	 *  - If the checkbox fields are *NOT* present in $data, then the member
	 *    will be removed from the group with the same codename.
	 *
	 * @param array $checkboxes An array list of the checkbox fieldnames (only
	 *               	          values are used). E.g. array(0, 1, 2)
	 * @param array $data The form data. Uually in the format array(0 => 2)
	 *                    (just pass the checkbox data from your form)
	 */
	function setByCheckboxes(array $checkboxes, array $data) {
		foreach($checkboxes as $checkbox) {
			if($data[$checkbox]) {
				$add[] = $checkbox;
			} else {
				$remove[] = $checkbox;
			}
		}

		if($add)
			$this->addManyByCodename($add);

		if($remove)
			$this->removeManyByCodename($remove);
	}


	/**
	 * Allows you to set groups based on a CheckboxSetField
	 *
	 * Pass the form element from your post data directly to this method, and
	 * it will update the groups and add and remove the member as appropriate.
	 *
	 * On the form setup:
	 *
	 * <code>
	 * $fields->push(
	 *   new CheckboxSetField(
	 *     "NewsletterSubscriptions",
	 *     "Receive email notification of events in ",
	 *     $sourceitems = DataObject::get("NewsletterType")->toDropDownMap("GroupID","Title"),
	 *     $selectedgroups = $member->Groups()->Map("ID","ID")
	 *   )
	 * );
	 * </code>
	 *
	 * On the form handler:
	 *
	 * <code>
	 * $groups = $member->Groups();
	 * $checkboxfield = $form->Fields()->fieldByName("NewsletterSubscriptions");
	 * $groups->setByCheckboxSetField($checkboxfield);
	 * </code>
	 *
	 * @param CheckboxSetField $checkboxsetfield The CheckboxSetField (with
	 *                                           data) from your form.
	 */
	function setByCheckboxSetField(CheckboxSetField $checkboxsetfield) {
		// Get the values from the formfield.
		$values = $checkboxsetfield->Value();
		$sourceItems = $checkboxsetfield->getSource();

		if($sourceItems) {
			// If (some) values are present, add and remove as necessary.
			if($values) {
				// update the groups based on the selections
				foreach($sourceItems as $k => $item) {
					if(in_array($k,$values)) {
						$add[] = $k;
					} else {
						$remove[] = $k;
					}
				}

			// else we should be removing all from the necessary groups.
			} else {
				$remove = $sourceItems;
			}

			if($add)
				$this->addManyByGroupID($add);

			if($remove)
				$this->RemoveManyByGroupID($remove);

		} else {
			USER_ERROR("Member::setByCheckboxSetField() - No source items could be found for checkboxsetfield " .
								 $checkboxsetfield->Name(), E_USER_WARNING);
		}
	}


	/**
	 * Adds this member to the groups based on the group IDs
	 *
	 * @param array $ids Group identifiers.
	 */
	function addManyByGroupID($groupIds){
		$groups = $this->getGroupsFromIDs($groupIds);
		if($groups) {
			foreach($groups as $group) {
				$this->add($group);
			}
		}
	}


	/**
	 * Removes the member from many groups based on the group IDs
	 *
	 * @param array $ids Group identifiers.
	 */
	function removeManyByGroupID($groupIds) {
	 	$groups = $this->getGroupsFromIDs($groupIds);
	 	if($groups) {
			foreach($groups as $group) {
				$this->remove($group);
			}
		}
	}


	/**
	 * Returns the groups from an array of group IDs
	 *
	 * @param array $ids Group identifiers.
	 * @return mixed Returns the groups from the array of Group IDs.
	 */
	function getGroupsFromIDs($ids){
		if($ids && count($ids) > 1) {
			return DataObject::get("Group", "ID IN (" . implode(",", $ids) . ")");
		} else {
			return DataObject::get_by_id("Group", $ids[0]);
		}
	}


	/**
	 * Adds this member to the groups based on the group codenames
	 *
	 * @param array $codenames Group codenames
	 */
	function addManyByCodename($codenames) {
		$groups = $this->codenamesToGroups($codenames);
		if($groups) {
			foreach($groups as $group){
				$this->add($group);
			}
		}
	}


	/**
	 * Removes this member from the groups based on the group codenames
	 *
	 * @param array $codenames Group codenames
	 */
	function removeManyByCodename($codenames) {
		$groups = $this->codenamesToGroups($codenames);
		if($groups) {
			foreach($groups as $group) {
				$this->remove($group);
			}
		}
	}


	/**
	 * Helper function to return the appropriate groups via a codenames
	 *
	 * @param array $codenames Group codenames
	 * @return array Returns the the appropriate groups.
	 */
	protected function codenamesToGroups($codenames) {
		$list = "'" . implode("', '", $codenames) . "'";
		$output = DataObject::get("Group", "Code IN ($list)");

		// Some are missing - throw warnings
		if(!$output || ($output->Count() != sizeof($list))) {
			foreach($codenames as $codename)
				$missing[$codename] = $codename;

			if($output) {
				foreach($output as $record)
					unset($missing[$record->Code]);
			}

			if($missing)
				user_error("The following group-codes aren't matched to any groups: " .
									 implode(", ", $missing) .
									 ".  You probably need to link up the correct group codes in phpMyAdmin",
									 E_USER_WARNING);
		}

		return $output;
	}
}



/**
 * Class used as template to send an email to new members
 */
class Member_SignupEmail extends Email_Template {
	protected
		$from = '',  // setting a blank from address uses the site's default administrator email
		$to = '$Email',
		$subject = "Thanks for signing up",
		$body = '
			<h1>Welcome, $FirstName.</h1>
			<p>Thanks for signing up to become a new member, your details are listed below for future reference.</p>

			<p>You can login to the website using the credentials listed below:
				<ul>
					<li><strong>Email:</strong>$Email</li>
					<li><strong>Password:</strong>$Password</li>
				</ul>
			</p>

			<h3>Contact Information</h3>
			<ul>
				<li><strong>Name:</strong> $FirstName $Surname</li>
				<% if Phone %>
					<li><strong>Phone:</strong> $Phone</li>
				<% end_if %>

				<% if Mobile %>
					<li><strong>Mobile:</strong> $Mobile</li>
				<% end_if %>

				<% if RuralAddressCheck %>
					<li><strong>Rural Address:</strong>
						$RapidResponse $Road<br/>
						$RDNumber<br/>
						$City $Postcode
					</li>
				<% else %>
					<li><strong>Address:</strong>
					<br/>
					$Number $Street $StreetType<br/>
					$Suburb<br/>
					$City $Postcode
					</li>
				<% end_if %>

				<% if DriversLicense5A %>
					<li><strong>Drivers License:</strong> $DriversLicense5A<% if DriversLicense5B %> - $DriversLicense5B <% end_if %></li>
				<% end_if %>

			</ul>';

	function MemberData() {
		return $this->template_data->listOfFields(
			"FirstName", "Surname", "Email",
			"Phone", "Mobile", "Street",
			"Suburb", "City", "Postcode", "DriversLicense5A", "DriversLicense5B"
		);
	}
}



/**
 * Class used as template to send an email saying that the password has been
 * changed
 */
class Member_ChangePasswordEmail extends Email_Template {
    protected $from = '';   // setting a blank from address uses the site's default administrator email
    protected $subject = "Your password has been changed";
    protected $ss_template = 'ChangePasswordEmail';
    protected $to = '$Email';
}



/**
 * Class used as template to send the forgot password email
 */
class Member_ForgotPasswordEmail extends Email_Template {
    protected $from = '';  // setting a blank from address uses the site's default administrator email
    protected $subject = "Your password";
    protected $ss_template = 'ForgotPasswordEmail';
    protected $to = '$Email';
}



/**
 * Record to keep track of which records a member has unsubscribed from and
 * when
 *
 * @todo Check if that email stuff ($from, $to, $subject, $body) is needed
 *       here! (Markus)
 */
class Member_UnsubscribeRecord extends DataObject {

	static $has_one = array(
		'NewsletterType' => 'NewsletterType',
		'Member' => 'Member'
	);


	/**
	 * Unsubscribe the member from a specific newsletter type
	 *
	 * @param int|Member $member Member object or ID
	 * @param int|NewsletterType $newsletterType Newsletter type object or ID
	 */
	function unsubscribe($member, $newsletterType) {
		// $this->UnsubscribeDate()->setVal( 'now' );
		$this->MemberID = (is_numeric($member))
			? $member
			: $member->ID;

		$this->NewsletterTypeID = (is_numeric($newletterType))
			? $newsletterType
			: $newsletterType->ID;

		$this->write();
	}


	protected
		$from = '',  // setting a blank from address uses the site's default administrator email
		$to = '$Email',
		$subject = "Your password has been changed",
		$body = '
			<h1>Here\'s your new password</h1>
			<p>
				<strong>Email:</strong> $Email<br />
				<strong>Password:</strong> $Password
			</p>
			<p>Your password has been changed. Please keep this email, for future reference.</p>';
}



/**
 * Member Validator
 */
class Member_Validator extends RequiredFields {

	protected $customRequired = array('FirstName', 'Email'); //, 'Password');


	/**
	 * Constructor
	 */
	public function __construct() {
		$required = func_get_args();
		if(isset($required[0]) && is_array($required[0])) {
			$required = $required[0];
		}
		$required = array_merge($required, $this->customRequired);

		parent::__construct($required);
	}


	/**
	 * Check if the submitted member data is valid
	 *
	 * Check if a member with that email doesn't already exist, or if it does
	 * that it is this member.
	 *
	 * @param array $data Submitted data
	 * @return bool Returns TRUE if the submitted data is valid, otherwise
	 *              FALSE.
	 */
	function php($data) {
		$valid = parent::php($data);

		$member = DataObject::get_one('Member',
			"Email = '". Convert::raw2sql($data['Email']) ."'");

		// if we are in a complex table field popup, use ctf[childID], else use
		// ID
		$id = (isset($_REQUEST['ctf']['childID']))
			? $_REQUEST['ctf']['childID']
			: $_REQUEST['ID'];

		if(is_object($member) && $member->ID != $id) {
			$emailField = $this->form->dataFieldByName('Email');
			$this->validationError($emailField->id(),
														 "There already exists a member with this email",
														 "required");
			$valid = false;
		}

		return $valid;
	}
}


?>