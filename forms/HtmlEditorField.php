<?php
/**
 * A TinyMCE-powered WYSIWYG HTML editor field with image and link insertion and tracking capabilities. Editor fields
 * are created from <textarea> tags, which are then converted with JavaScript.
 *
 * @package forms
 * @subpackage fields-formattedinput
 */
class HtmlEditorField extends TextareaField {
	
	/**
	 * Includes the JavaScript neccesary for this field to work using the {@link Requirements} system.
	 */
	public static function include_js() {
		Requirements::javascript(MCE_ROOT . 'tiny_mce_src.js');
		Requirements::customScript(HtmlEditorConfig::get_active()->generateJS(), 'htmlEditorConfig');
	}
	
	/**
	 * @see TextareaField::__construct()
	 */
	public function __construct($name, $title = null, $rows = 30, $cols = 20, $value = '', $form = null) {
		parent::__construct($name, $title, $rows, $cols, $value, $form);
		
		$this->addExtraClass('typography');
		$this->addExtraClass('htmleditor');
		
		self::include_js();
	}
	
	/**
	 * @return string
	 */
	function Field() {
		// mark up broken links
		$value  = new SS_HTMLValue($this->value);
		
		if($links = $value->getElementsByTagName('a')) foreach($links as $link) {
			$matches = array();
			
			if(preg_match('/\[sitetree_link id=([0-9]+)\]/i', $link->getAttribute('href'), $matches)) {
				if(!DataObject::get_by_id('SiteTree', $matches[1])) {
					$class = $link->getAttribute('class');
					$link->setAttribute('class', ($class ? "$class ss-broken" : 'ss-broken'));
				}
			}
		}
		
		return $this->createTag (
			'textarea',
			array (
				'class'   => $this->extraClass(),
				'rows'    => $this->rows,
				'cols'    => $this->cols,
				'style'   => 'width: 97%; height: ' . ($this->rows * 16) . 'px', // prevents horizontal scrollbars
				'tinymce' => 'true',
				'id'      => $this->id(),
				'name'    => $this->name
			),
			htmlentities($value->getContent(), ENT_COMPAT, 'UTF-8')
		);
	}
	
	public function saveInto($record) {
		if($record->escapeTypeForField($this->name) != 'xml') {
			throw new Exception (
				'HtmlEditorField->saveInto(): This field should save into a HTMLText or HTMLVarchar field.'
			);
		}
		
		$linkedPages = array();
		$linkedFiles = array();
		
		$record->HasBrokenFile = false;
		$record->HasBrokenLink = false;
		
		$htmlValue = new SS_HTMLValue($this->value);
		
		// Populate link tracking for internal links & links to asset files.
		if($links = $htmlValue->getElementsByTagName('a')) foreach($links as $link) {
			$href = Director::makeRelative($link->getAttribute('href'));
			
			if($href) {
				if(preg_match('/\[sitetree_link id=([0-9]+)\]/i', $href, $matches)) {
					$ID = $matches[1];
					
					// clear out any broken link classes
					if($class = $link->getAttribute('class')) {
						$link->setAttribute('class', preg_replace('/(^ss-broken|ss-broken$| ss-broken )/', null, $class));
					}
					
					if($page = DataObject::get_by_id('SiteTree', $ID)) {
						$linkedPages[] = $page->ID;
					} else {
						$record->HasBrokenLink = true;
					}
				} elseif($href[0] != '/' && $file = File::find($href)) {
					$linkedFiles[] = $file->ID;
				}
			}
		}
		
		// Resample images, add default attributes and add to assets tracking.
		if($images = $htmlValue->getElementsByTagName('img')) foreach($images as $img) {
			// strip any ?r=n data from the src attribute
			$img->setAttribute('src', preg_replace('/([^\?]*)\?r=[0-9]+$/i', '$1', $img->getAttribute('src')));
			
			if(!$image = File::find($path = Director::makeRelative($img->getAttribute('src')))) {
				if(substr($path, 0, strlen(ASSETS_DIR) + 1) == ASSETS_DIR . '/') {
					$record->HasBrokenFile = true;
				}
				
				continue;
			}
			
			// Resample the images if the width & height have changed.
			$width  = $img->getAttribute('width');
			$height = $img->getAttribute('height');
			
			if($width && $height && ($width != $image->getWidth() || $height != $image->getHeight())) {
				$img->setAttribute('src', $image->ResizedImage($width, $height)->getRelativePath());
			}
			
			// Add default empty title & alt attributes.
			if(!$img->getAttribute('alt')) $img->setAttribute('alt', '');
			if(!$img->getAttribute('title')) $img->setAttribute('title', '');
			
			// Add to the tracked files.
			$linkedFiles[] = $image->ID;
		}
		
		// Save file & link tracking data.
		if($record->ID && $record->many_many('LinkTracking') && $tracker = $record->LinkTracking()) {
			$filter = sprintf('"FieldName" = \'%s\' AND "SiteTreeID" = %d', $this->name, $record->ID);
			DB::query("DELETE FROM \"$tracker->tableName\" WHERE $filter");

			if($linkedPages) foreach($linkedPages as $item) {
				$tracker->add($item, array('FieldName' => $this->name));
			}
		}
		
		if($record->ID && $record->many_many('ImageTracking') && $tracker = $record->ImageTracking()) {
			$filter = sprintf('"FieldName" = \'%s\' AND "SiteTreeID" = %d', $this->name, $record->ID);
			DB::query("DELETE FROM \"$tracker->tableName\" WHERE $filter");

			$fieldName = $this->name;
			if($linkedFiles) foreach($linkedFiles as $item) {
				$tracker->add($item, array('FieldName' => $this->name));
			}
		}
		
		$record->{$this->name} = $htmlValue->getContent();
	}

	/**
	 * @return HtmlEditorField_Readonly
	 */
	public function performReadonlyTransformation() {
		$field = new HtmlEditorField_Readonly($this->name, $this->title, $this->value);
		$field->setForm($this->form);
		$field->dontEscape = true;
		return $field;
	}
	
}

/**
 * Readonly version of an {@link HTMLEditorField}.
 * @package forms
 * @subpackage fields-formattedinput
 */
class HtmlEditorField_Readonly extends ReadonlyField {
	function Field() {
		$valforInput = $this->value ? Convert::raw2att($this->value) : "";
		return "<span class=\"readonly typography\" id=\"" . $this->id() . "\">" . ( $this->value && $this->value != '<p></p>' ? $this->value : '<i>(not set)</i>' ) . "</span><input type=\"hidden\" name=\"".$this->name."\" value=\"".$valforInput."\" />";
	}
	function Type() {
		return 'htmleditorfield readonly';
	}
}

/**
 * External toolbar for the HtmlEditorField.
 * This is used by the CMS
 * @package forms
 * @subpackage fields-formattedinput
 */
class HtmlEditorField_Toolbar extends RequestHandler {
	protected $controller, $name;
	
	function __construct($controller, $name) {
		parent::__construct();
		
		$this->controller = $controller;
		$this->name = $name;
	}
	
	/**
	 * Return a {@link Form} instance allowing a user to
	 * add links in the TinyMCE content editor.
	 *  
	 * @return Form
	 */
	function LinkForm() {
		Requirements::javascript(SAPPHIRE_DIR . "/thirdparty/behaviour/behaviour.js");
		Requirements::javascript(SAPPHIRE_DIR . "/javascript/tiny_mce_improvements.js");

		$form = new Form(
			$this->controller,
			"{$this->name}/LinkForm", 
			new FieldSet(
				new LiteralField(
					'Heading', 
					sprintf('<h3>%s</h3>', _t('HtmlEditorField.LINK', 'Link'))
				),
				$contentComposite = new CompositeField(
					new OptionsetField(
						'LinkType',
						_t('HtmlEditorField.LINKTO', 'Link to'), 
						array(
							'internal' => _t('HtmlEditorField.LINKINTERNAL', 'Page on the site'),
							'external' => _t('HtmlEditorField.LINKEXTERNAL', 'Another website'),
							'anchor' => _t('HtmlEditorField.LINKANCHOR', 'Anchor on this page'),
							'email' => _t('HtmlEditorField.LINKEMAIL', 'Email address'),
							'file' => _t('HtmlEditorField.LINKFILE', 'Download a file'),			
						)
					),
					new TreeDropdownField('internal', _t('HtmlEditorField.PAGE', "Page"), 'SiteTree', 'URLSegment', 'MenuTitle'),
					new TextField('external', _t('HtmlEditorField.URL', 'URL'), 'http://'),
					new EmailField('email', _t('HtmlEditorField.EMAIL', 'Email address')),
					new TreeDropdownField('file', _t('HtmlEditorField.FILE', 'File'), 'File', 'Filename'),
					new TextField('Anchor', _t('HtmlEditorField.ANCHORVALUE', 'Anchor')),
					new TextField('LinkText', _t('HtmlEditorField.LINKTEXT', 'Link text')),
					new TextField('Description', _t('HtmlEditorField.LINKDESCR', 'Link description')),
					new CheckboxField('TargetBlank', _t('HtmlEditorField.LINKOPENNEWWIN', 'Open link in a new window?'))
				)
			),
			new FieldSet(
				new FormAction('insert', _t('HtmlEditorField.BUTTONINSERTLINK', 'Insert link')),
				new FormAction('remove', _t('HtmlEditorField.BUTTONREMOVELINK', 'Remove link'))
			)
		);
		
		$contentComposite->addExtraClass('content');
		
		$form->unsetValidator();
		$form->loadDataFrom($this);
		
		return $form;
	}

	/**
	 * Return a {@link Form} instance allowing a user to
	 * add images to the TinyMCE content editor.
	 *  
	 * @return Form
	 */
	function ImageForm() {
		Requirements::javascript(SAPPHIRE_DIR . "/thirdparty/behaviour/behaviour.js");
		Requirements::javascript(SAPPHIRE_DIR . "/javascript/tiny_mce_improvements.js");
		Requirements::css('cms/css/TinyMCEImageEnhancement.css');
		Requirements::javascript(CMS_DIR . '/javascript/Upload.js');
		Requirements::javascript(SAPPHIRE_DIR . '/thirdparty/swfupload/swfupload/swfupload.js');
		Requirements::javascript('cms/javascript/TinyMCEImageEnhancement.js');

		/**
		 * @todo Adding folders via this screen is not enabled just yet as it is still
		 *			a bit too buggy - wrossiter (09/11/09)
		 */
		$form = new Form(
			$this->controller,
			"{$this->name}/ImageForm",
			new FieldSet(
				new LiteralField(
					'Heading', 
					sprintf('<h3>%s</h3>', _t('HtmlEditorField.IMAGE', 'Image'))
				),
				$contentComposite = new CompositeField(
					new TreeDropdownField('FolderID', _t('HtmlEditorField.FOLDER', 'Folder'), 'Folder'),
					new LiteralField('AddFolderOrUpload',
						'<div style="clear:both;"></div><div id="AddFolderGroup" style="display: none">
							<a style="" href="#" id="AddFolder" class="link">' . _t('HtmlEditorField.CREATEFOLDER','Create Folder') . '</a>
							<input style="display: none; margin-left: 2px; width: 94px;" id="NewFolderName" class="addFolder" type="text">
							<a style="display: none;" href="#" id="FolderOk" class="link addFolder">' . _t('HtmlEditorField.OK','Ok') . '</a>
							<a style="display: none;" href="#" id="FolderCancel" class="link addFolder">' . _t('HtmlEditorField.FOLDERCANCEL','Cancel') . '</a>
						</div>
						<div id="UploadGroup" class="group">
							<a href="#" id="SWFUploadButton" class="link">' . _t('HtmlEditorField.UPLOAD','Upload') . '</a>
						</div>' .
						'<div id="UploadFiles"></div>'
					),
					new TextField('getimagesSearch', _t('HtmlEditorField.SEARCHFILENAME', 'Search by file name')),
					new ThumbnailStripField('FolderImages', 'FolderID', 'getimages'),
					new TextField('AltText', _t('HtmlEditorField.IMAGEALTTEXT', 'Alternative text (alt) - shown if image cannot be displayed'), '', 80),
					new TextField('ImageTitle', _t('HtmlEditorField.IMAGETITLE', 'Title text (tooltip) - for additional information about the image')),
					new TextField('CaptionText', _t('HtmlEditorField.CAPTIONTEXT', 'Caption text')),
					new DropdownField(
						'CSSClass',
						_t('HtmlEditorField.CSSCLASS', 'Alignment / style'),
						array(
							'left' => _t('HtmlEditorField.CSSCLASSLEFT', 'On the left, with text wrapping around.'),
							'leftAlone' => _t('HtmlEditorField.CSSCLASSLEFTALONE', 'On the left, on its own.'),
							'right' => _t('HtmlEditorField.CSSCLASSRIGHT', 'On the right, with text wrapping around.'),
							'center' => _t('HtmlEditorField.CSSCLASSCENTER', 'Centered, on its own.'),
						)
					),
					new FieldGroup(_t('HtmlEditorField.IMAGEDIMENSIONS', 'Dimensions'),
						new TextField('Width', _t('HtmlEditorField.IMAGEWIDTHPX', 'Width'), 100),
						new TextField('Height', " x " . _t('HtmlEditorField.IMAGEHEIGHTPX', 'Height'), 100)
					)
				)
			),
			new FieldSet(
				new FormAction('insertimage', _t('HtmlEditorField.BUTTONINSERTIMAGE', 'Insert image'))
			)
		);
		
		$contentComposite->addExtraClass('content');
		
		$form->unsetValidator();
		$form->disableSecurityToken();
		$form->loadDataFrom($this);
		
		return $form;
	}

	function FlashForm() {
		Requirements::javascript(SAPPHIRE_DIR . "/thirdparty/behaviour/behaviour.js");
		Requirements::javascript(SAPPHIRE_DIR . "/javascript/tiny_mce_improvements.js");
		Requirements::javascript(SAPPHIRE_DIR . '/thirdparty/swfupload/swfupload/swfupload.js');
		Requirements::javascript(CMS_DIR . '/javascript/Upload.js');

		$form = new Form(
			$this->controller,
			"{$this->name}/FlashForm", 
			new FieldSet(
				new LiteralField(
					'Heading', 
					sprintf('<h3>%s</h3>', _t('HtmlEditorField.FLASH', 'Flash'))
				),
				$contentComposite = new CompositeField(
					new TreeDropdownField("FolderID", _t('HtmlEditorField.FOLDER'), "Folder"),
					new TextField('getflashSearch', _t('HtmlEditorField.SEARCHFILENAME', 'Search by file name')),
					new ThumbnailStripField("Flash", "FolderID", "getflash"),
					new FieldGroup(_t('HtmlEditorField.IMAGEDIMENSIONS', "Dimensions"),
						new TextField("Width", _t('HtmlEditorField.IMAGEWIDTHPX', "Width"), 100),
						new TextField("Height", "x " . _t('HtmlEditorField.IMAGEHEIGHTPX', "Height"), 100)
					)
				)
			),
			new FieldSet(
				new FormAction("insertflash", _t('HtmlEditorField.BUTTONINSERTFLASH', 'Insert Flash'))
			)
		);
		
		$contentComposite->addExtraClass('content');
		
		$form->unsetValidator();
		
		$form->loadDataFrom($this);
		$form->disableSecurityToken();
		return $form;
	}
}

