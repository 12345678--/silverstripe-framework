<?php
/**
 * Plug-ins for additional functionality in your DataObjects
 *
 * DataObject decorators add extra functionality to your data objects.
 * @package sapphire
 * @subpackage model
 */
abstract class DataObjectDecorator extends Extension implements i18nEntityProvider {

	/**
	 * Statics on a {@link DataObject} subclass
	 * which can be decorated onto. This list is
	 * limited for security and performance reasons.
	 *
	 * @var array
	 */
	protected static $decoratable_statics = array(
		'db', 
		'has_one', 
		'indexes', 
		'defaults', 
		'has_many', 
		'many_many', 
		'belongs_many_many', 
		'many_many_extraFields',
		'searchable_fields',
	);
	
	/**
	 * Load the extra database fields defined in extraDBFields.
	 * 
	 * @todo Rename to "loadExtraStaticFields", as it decorates more than database related fields.
	 */
	function loadExtraDBFields() {
		// Don't apply DB fields if the parent object has this extension too
		if(singleton(get_parent_class($this->owner))->extInstance($this->class)) return;
	
		$fields = $this->extraDBFields();
		$className = $this->owner->class;

		if($fields) {
			foreach($fields as $relationType => $fields) {
				if(in_array($relationType, self::$decoratable_statics)) {
					eval("$className::\$$relationType = array_merge((array){$className}::\$$relationType, (array)\$fields);");
					$this->owner->set_stat($relationType, eval("return $className::\$$relationType;"));
				}
				
				// clear previously set caches from DataObject->hasOwnTableDatabaseField()
				$this->owner->set_uninherited('_cache_hasOwnTableDatabaseField', null);
			}
		}
	}


	/**
	 * Edit the given query object to support queries for this extension
	 *
	 * @param SQLQuery $query Query to augment.
	 */
	function augmentSQL(SQLQuery &$query) {
	}


	/**
	 * Update the database schema as required by this extension.
	 */
	function augmentDatabase() {
	}
	
	/**
	 * Augment a write-record request.
	 *
	 * @param SQLQuery $manipulation Query to augment.
	 */
	function augmentWrite(&$manipulation) {
	}


	/**
	 * Define extra database fields
	 *
	 * Return a map where the keys are db, has_one, etc, and the values are
	 * additional fields/relations to be defined.
	 * 
	 * Note: please ensure that the static variable that you are overloading is explicitly defined on the class that
	 * you are extending.  For example, we have added static $has_one = array() to the Member definition, so that we
	 * can add has_one relationships to Member with decorators.
	 * 
	 * If you forget to do this, db/build won't create the new relation.  Don't blame us, blame PHP! ;-)
	 *
	 * @return array Returns a map where the keys are db, has_one, etc, and
	 *               the values are additional fields/relations to be defined.
	 */
	function extraDBFields() {
		return array();
	}


	/**
	 * This function is used to provide modifications to the form in the CMS
	 * by the decorator. By default, no changes are made.
	 * Please consider using {@link updateFormFields()} to globally add
	 * formfields to the record. The method {@link updateCMSFields()}
	 * should just be used to add or modify tabs, or fields which
	 * are specific to the CMS-context.
	 *
	 * Caution: Use {@link FieldSet->addFieldToTab()} to add fields.
	 *
	 * @param FieldSet $fields FieldSet with a contained TabSet
	 */
	function updateCMSFields(FieldSet &$fields) {
	}
	
	/**
	 * This function is used to provide modifications to the form in the CMS
	 * by the decorator.
	 * 
	 * Caution: Use {@link FieldSet->push()} to add fields.
	 *
	 * @param FieldSet $fields FieldSet without TabSet nesting
	 */
	function updateFormFields(FieldSet &$fields) {
	}
	
	/**
	 * this function is used to provide modifications to the summary fields in CMS
	 * by the decorator
	 * By default, the summaryField() of its owner will merge more fields defined in the decorator's
	 * $extra_fields['summary_fields']
	 */
	function updateSummaryFields(&$fields){
		$extra_fields = $this->extraDBFields();
		if(isset($extra_fields['summary_fields'])){
			$summary_fields = $extra_fields['summary_fields'];
			if($summary_fields) $fields = array_merge($fields, $summary_fields);
		}
	}
	
	/**
	 * this function is used to provide modifications to the fields labels in CMS
	 * by the decorator
	 * By default, the fieldLabels() of its owner will merge more fields defined in the decorator's
	 * $extra_fields['field_labels']
	 */
	function updateFieldLabels(&$lables){
		$extra_fields = $this->extraDBFields();
		if(isset($extra_fields['field_labels'])){
			$field_labels = $extra_fields['field_labels'];
			if($field_labels) $lables = array_merge($lables, $field_labels);
		}
	}
	
	function provideI18nEntities() {
		$entities = array();
		$fields = $this->extraDBFields();
		$translatableAttributes = array('db','has_one','has_many','many_many');
		foreach($fields as $att => $spec) {
			if(!in_array($att, $translatableAttributes)) continue;
			
			foreach($spec as $name => $type) {
				$entities["{$this->class}.{$att}_{$name}"] = array($name);
			}
		}

		return $entities;
	}

}

?>
