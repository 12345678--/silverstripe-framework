<?php


require_once('HTML/BBCodeParser.php');
/*Seting up the PEAR bbcode parser*/  
$config = parse_ini_file('BBCodeParser.ini', true);
$options = &PEAR::getStaticProperty('HTML_BBCodeParser', '_options');
$options = $config['HTML_BBCodeParser'];
//Debug::show($options);
unset($options);

/**
 * TODO Investigate whether SSViewer will be fast enough to handle hundreds of little template files.
 * 
 * A better (more SS) way of doing the HTML code in here is to place them all in small template files
 * (eg. BBCodeParser_Code.ss contains the HTML for BBCodeParser::parseCode()), but the overhead this 
 * would cause is likely to make this very unusable, as nice as it would be.
 */
class BBCodeParser extends TextParser {
	static function usable_tags() {
		return new DataObjectSet(
			new ArrayData(array(
				"Title" => "Bold Text",
				"Example" => "[b]<b>Bold</b>[/b]"
			)),
			new ArrayData(array(
				"Title" => "Italic Text",
				"Example" => "[i]<i>Italics</i>[/i]"
			)),
			new ArrayData(array(
				"Title" => "Underlined Text",
				"Example" => "[u]<u>Underlined</u>[/u]"
			)),
			new ArrayData(array(
				"Title" => "Struck-out Text",
				"Example" => "[s]<s>Struck-out</s>[/s]"
			)),
			
			new ArrayData(array(
				"Title" => "Website link",
				"Description" => "Link to another website or URL",
				"Example" => "[url]http://www.website.com/[/url]"
			)),
			new ArrayData(array(
				"Title" => "Website link",
				"Description" => "Link to another website or URL",
				"Example" => "[url=http://www.website.com/]Some website[/url]"
			)),			
			new ArrayData(array(
				"Title" => "Email link",
				"Description" => "Create link to an email address",
				"Example" => "[email]you@yoursite.com[/email]"
			)),
				new ArrayData(array(
				"Title" => "Email link",
				"Description" => "Create link to an email address",
				"Example" => "[email=you@yoursite.com]email me[/email]"
			)),		

			new ArrayData(array(
				"Title" => "Image",
				"Description" => "Show an image in your post",
				"Example" => "[img]http://www.website.com/image.jpg[/img]"
			)),
			
			new ArrayData(array(
				"Title" => "Code Block",
				"Description" => "Unformatted code block",
				"Example" => "[code]Code block[/code]"
			)),
			new ArrayData(array(
				"Title" => "HTML Code Block",
				"Description" => "HTML-formatted code block",
				"Example" => "[html]HTML code block[/html]"
			)),
			new ArrayData(array(
				"Title" => "HTML Code Block",
				"Description" => "HTML-formatted code block",
				"Example" => "[code html]HTML code block[/code]"
			)),				
			new ArrayData(array(
				"Title" => "PHP Code Block",
				"Description" => "PHP-formatted code block",
				"Example" => "[php]PHP code block[/php]"
			)),
			new ArrayData(array(
				"Title" => "PHP Code Block",
				"Description" => "PHP-formatted code block",
				"Example" => "[code php]PHP code block[/code]"
			))			
					
		);
	}
	
	function useable_tagsHTML(){
		$useabletags = "<ul class='bbcodeExamples'>";
		foreach($this->usable_tags()->toArray() as $tag){
			$useabletags = $useabletags."<li><span>".$tag->Example."</span></li>";
		}
		return $useabletags."</ul>";
	}
	

	function parse() {
		$this->content = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $this->content);
		return HTML_BBCodeParser::staticQparse($this->content);
	}
	
	
}
?>