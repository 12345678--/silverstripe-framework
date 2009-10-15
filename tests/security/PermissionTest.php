<?php

class PermissionTest extends SapphireTest {
	static $fixture_file = 'sapphire/tests/security/PermissionTest.yml';
	
	function testDirectlyAppliedPermissions() {
		$member = $this->objFromFixture('Member', 'author');
		$this->assertTrue(Permission::checkMember($member, "SITETREE_VIEW_ALL"));
	}
	
	function testPermissionAreInheritedFromOneRole() {
		$member = $this->objFromFixture('Member', 'author');
		$this->assertTrue(Permission::checkMember($member, "CMS_ACCESS_CMSMain"));
		$this->assertTrue(Permission::checkMember($member, "CMS_ACCESS_AssetAdmin"));
		$this->assertFalse(Permission::checkMember($member, "CMS_ACCESS_SecurityAdmin"));
	}

	function testPermissionAreInheritedFromMultipleRoles() {
		$member = $this->objFromFixture('Member', 'access');
		$this->assertTrue(Permission::checkMember($member, "CMS_ACCESS_CMSMain"));
		$this->assertTrue(Permission::checkMember($member, "CMS_ACCESS_AssetAdmin"));
		$this->assertTrue(Permission::checkMember($member, "CMS_ACCESS_SecurityAdmin"));
		$this->assertTrue(Permission::checkMember($member, "EDIT_PERMISSIONS"));
		$this->assertFalse(Permission::checkMember($member, "SITETREE_VIEW_ALL"));
	}
	
	function testRolesAndPermissionsFromParentGroupsAreInherited() {
		$member = $this->objFromFixture('Member', 'globalauthor');
		
		// Check that permissions applied to the group are there
		$this->assertTrue(Permission::checkMember($member, "SITETREE_EDIT_ALL"));
		
		// Check that roles from parent groups are there
		$this->assertTrue(Permission::checkMember($member, "CMS_ACCESS_CMSMain"));
		$this->assertTrue(Permission::checkMember($member, "CMS_ACCESS_AssetAdmin"));

		// Check that permissions from parent groups are there
		$this->assertTrue(Permission::checkMember($member, "SITETREE_VIEW_ALL"));
		
		// Check that a random permission that shouldn't be there isn't
		$this->assertFalse(Permission::checkMember($member, "CMS_ACCESS_SecurityAdmin"));
	}
	
}