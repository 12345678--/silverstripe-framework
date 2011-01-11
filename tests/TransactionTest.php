<?php
/**
 * @package sapphire
 * @subpackage tests
 */
class TransactionTest extends SapphireTest {

	function testCreateWithTransaction() {

		if(DB::getConn()->supportsTransactions()==true){
			DB::getConn()->transactionStart();
			$page=new Page();
			$page->Title='First page';
			$page->write();

			$page=new Page();
			$page->Title='Second page';
			$page->write();

			//Create a savepoint here:
			DB::getConn()->transactionSavepoint('rollback');

			$page=new Page();
			$page->Title='Third page';
			$page->write();

			$page=new Page();
			$page->Title='Forth page';
			$page->write();

			//Revert to a savepoint:
			DB::getConn()->transactionRollback('rollback');

			DB::getConn()->transactionEnd();

			$first=DataObject::get('Page', "\"Title\"='First page'");
			$second=DataObject::get('Page', "\"Title\"='Second page'");
			$third=DataObject::get('Page', "\"Title\"='Third page'");
			$forth=DataObject::get('Page', "\"Title\"='Forth page'");

			//These pages should be in the system
			$this->assertTrue(is_object($first) && $first->exists());
			$this->assertTrue(is_object($second) && $second->exists());

			//These pages should NOT exist, we reverted to a savepoint:
			$this->assertFalse(is_object($third) && $third->exists());
			$this->assertFalse(is_object($forth) && $forth->exists());
		}
	}

	function testReadOnlyTransaction(){

		if(DB::getConn()->supportsTransactions()==true){

			$page=new Page();
			$page->Title='Read only success';
			$page->write();

			DB::getConn()->transactionStart('READ ONLY');

			try {
				$page=new Page();
				$page->Title='Read only page failed';
				$page->write();
			} catch (Exception $e) {
				//could not write this record
				//We need to do a rollback or a commit otherwise we'll get error messages
				DB::getConn()->transactionRollback();
			}

			DB::getConn()->transactionEnd();

			DataObject::flush_and_destroy_cache();

			$success=DataObject::get('Page', "\"Title\"='Read only success'");
			$fail=DataObject::get('Page', "\"Title\"='Read only page failed'");

			//This page should be in the system
			$this->assertTrue(is_object($success) && $success->exists());

			//This page should NOT exist, we had 'read only' permissions
			$this->assertFalse(is_object($fail) && $fail->exists());

		}

	}

}

?>