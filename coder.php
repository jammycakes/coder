<?php

/*
Plugin Name: Coder
Plugin URI: http://jamesmckay.net/code/coder/
Description: Automatically escape code within &lt;code&gt;...&lt;/code&gt; tag and highlights it using Alex Gorbatchev's javascript based Syntax Highlighter. Based on Code Auto Escape by Priyadi Iman Nurcahyo and SyntaxHighlighter by matt, Viper007Bond and mdawaffe. 
Version: 1.0 alpha 1
Author: James McKay 
Author URI: http://jamesmckay.net/
*/



class jm_coder
{
	/* ====== Display options ====== */
	
	var $opts;
	
	var $numbers = true;
	var $controls = true;
	var $collapse = false;
	var $columns = false;


	/* ====== [ Code Auto Escape ] ====== */
	
	/* ====== mask code ====== */
	
	function mask($text) {
		// Quick optimisation if there are no code blocks
		if (stripos($text, '<code') === false) {
			return $text;
		}
		$textarr = preg_split("/(<code[^>]*>.*<\\/code>)/Us", $text, -1, PREG_SPLIT_DELIM_CAPTURE); // capture the tags as well as in between
		$stop = count($textarr);// loop stuff
		for ($i = 0; $i < $stop; $i++) {
			$content = $textarr[$i];
			if (preg_match("/^<code[^>]*>(.*)<\\/code>/Us", $content, $code)) { // If it's a code	
				$content = '[code]' . base64_encode($code[1]) . '[/code]';
			}
			$output .= $content;
		}
		return $output;
	}


	/* ====== unmask code ====== */
	
	function unmask($text, $replace = false, $addpre = false) {
		// Quick optimisation if there are no code blocks
		if (stripos ($text, '[code]') === false) {
			return $text;
		}

		$textarr = preg_split("/(\\[code\\].*\\[\\/code\\])/Us", $text, -1, PREG_SPLIT_DELIM_CAPTURE); // capture the tags as well as in between
		$stop = count($textarr);// loop stuff
		for ($i = 0; $i < $stop; $i++) {
			$content = $textarr[$i];
			if (preg_match("/^\\[code\\](.*)\\[\\/code\\]/Us", $content, $code)) { // If it's a code
				$content = base64_decode($code[1]);
				if ($replace) {
					$content = preg_replace("/<\\\\\\/([^>]+)>/", '</$1>', $content);
					$content = preg_replace("/\\r/", '', $content);
					$content = preg_replace("/^\\s*?\\n/", "\n", $content);
					$content = preg_replace("/&/", '&amp;', $content);
					$content = preg_replace("/</", '&lt;', $content);
					$content = preg_replace("/>/", '&gt;', $content);
					$content = '<code>' . $content . '</code>';
					if ($addpre) {
						if (preg_match('/\\n/', $content)) {
							$content = "<pre>" . $content . "</pre>";
						}
					}
				} else {
					$content = "<code>" . $content . "</code>";
				}
			}
			$output .= $content;
		}
		return $output;
	}


	/* ====== unmask and do replacement ====== */
	
	function unmask_replace($text) {
		return $this->unmask($text, true);
	}
	
	
	/* ====== unmask and do replacement, plus enclose in <pre> ====== */

	function unmask_replace_addpre($text) {
		return $this->unmask($text, true, true);
	}


	/* ====== Code Auto Escape init ====== */
		
	function caeInit()
	{
		add_filter('content_save_pre', array(&$this, 'mask'), 28);
		add_filter('content_save_pre', array(&$this, 'unmask'), 72);
		add_filter('the_content', array(&$this, 'mask'), 1);
		add_filter('the_content', array(&$this, 'unmask_replace'), 99);
		
		add_filter('excerpt_save_pre', array(&$this, 'mask'), 28);
		add_filter('excerpt_save_pre', array(&$this, 'unmask'), 72);
		add_filter('the_excerpt', array(&$this, 'mask'), 1);
		add_filter('the_excerpt', array(&$this, 'unmask_replace'), 99);
		
		add_filter('pre_comment_content', array(&$this, 'mask'), 4);
		add_filter('pre_comment_content', array(&$this, 'unmask'), 36);
		add_filter('comment_save_pre', array(&$this, 'mask'), 28);
		add_filter('comment_save_pre', array(&$this, 'unmask'), 72);
		add_filter('comment_text', array(&$this, 'mask'), 1);
		add_filter('comment_text', array(&$this, 'unmask_replace_addpre'), 99);
	}
	
	
	/* ====== [ JavaScript style-up ] ====== */

	var $languages;
	var $scripts;

	function do_pre($tag)
	{
		if (stripos($tag[1], 'lang') === false) {
			return $tag[0];
		}
		
		$count = preg_match_all('/\\s(.*?)\\s*=\\s*([\'\"])(.*?)\\2/s', $tag[1], $matches);
		$attrs = array();
		for($i = 0; $i < $count; $i++) {
			$attrs[$matches[1][$i]] = $matches[3][$i];
		}

		$lang = $attrs['lang'];
		$script = $this->languages[$lang];
		$this->scripts[$script] = $lang;

		$attrs['name'] = 'code';
		$attrs['class'] = $attrs['lang'];
		unset($attrs['lang']);
		
		// Add the options
		
		foreach ($this->opts as $k => $v) {
			$a = $this->$k;
			if (isset ($attrs[$k])) {
				switch (strtolower($attrs[$k])) {
					case '1':
					case 'true':
					case 'on':
					case 'yes':
						$a = true;
						break;
					case '0':
					case 'off':
					case 'false':
					case 'no':
						$a = false;
				}
				unset ($attrs[$k]);
			}
			$attrs['class'] .= $v[$a ? 0 : 1];
		}
		
		if (isset($attrs['start'])) {
			$attrs['class'] .= ":firstline[$attrs[start]]";
			unset($attrs['start']);
		}
		
		$result = '<pre';
		foreach ($attrs as $k => $v) {
			$result .= ' ' . $k . '="' . $v . '"';
		}
		return $result . '>' . $tag[3] . '</pre>';
	}
	
	
	function get_scripts($text)
	{
		if (stripos($text, '<pre') === false) {
			return $text;
		}		
		return preg_replace_callback(
			'/<pre(\s[^>]*)?>(<code>)?(.*?)(<\/code>)?<\/pre>/is', 
			array(&$this, 'do_pre'), $text);
	}
	
	
	function write_scripts()
	{
		if (count($this->scripts) > 0) {
			$baseUrl = get_bloginfo( 'wpurl' ) . '/wp-content/plugins/coder/files';

			echo "<!-- SyntaxHighlighter Stuff -->\n";
			echo "<script type=\"text/javascript\" src=\"$baseUrl/shCore.js\"></script>\n";
			foreach ($this->scripts as $k => $v) {
				echo "<script type=\"text/javascript\" src=\"$baseUrl/$k\"></script>\n";
			}
			echo "<script type=\"text/javascript\">\n";
			echo "\tdp.SyntaxHighlighter.ClipboardSwf = '$baseUrl/clipboard.swf';\n";
			echo "\tdp.SyntaxHighlighter.HighlightAll('code');\n";
			echo "</script>\n";
		}
	}
	
	
	function add_stylesheet()
	{
		$baseUrl = get_bloginfo( 'wpurl' ) . '/wp-content/plugins/coder/files';
		echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"$baseUrl/SyntaxHighlighter.css\" />\n";
	}
	
	
	function jsInit()
	{
		add_filter('the_content', array(&$this, 'get_scripts'), 100); // Needs to be called after the last the_content in caeInit()
		add_filter('comment_text', array(&$this, 'get_scripts'), 100);
		add_action( 'wp_footer', array(&$this, 'write_scripts'), 1000 );
		add_action( 'admin_footer', array(&$this, 'write_scripts'), 1000 ); // For viewing comments in admin area
		add_action( 'wp_head', array(&$this, 'add_stylesheet'), 1000 );
		add_action( 'admin_head', array(&$this, 'add_stylesheet'), 1000 );
		add_action('admin_menu', array(&$this, 'add_config_page'));
		$this->scripts = array();
		$this->languages = array(
			'cpp'        => 'shBrushCpp.js',
			'c'          => 'shBrushCpp.js',
			'c++'        => 'shBrushCpp.js',
			'c#'         => 'shBrushCSharp.js',
			'c-sharp'    => 'shBrushCSharp.js',
			'csharp'     => 'shBrushCSharp.js',
			'css'        => 'shBrushCss.js',
			'delphi'     => 'shBrushDelphi.js',
			'pascal'     => 'shBrushDelphi.js',
			'java'       => 'shBrushJava.js',
			'js'         => 'shBrushJScript.js',
			'jscript'    => 'shBrushJScript.js',
			'javascript' => 'shBrushJScript.js',
			'php'        => 'shBrushPhp.js',
			'py'         => 'shBrushPython.js',
			'python'     => 'shBrushPython.js',
			'rb'         => 'shBrushRuby.js',
			'ruby'       => 'shBrushRuby.js',
			'rails'      => 'shBrushRuby.js',
			'ror'        => 'shBrushRuby.js',
			'sql'        => 'shBrushSql.js',
			'vb'         => 'shBrushVb.js',
			'vb.net'     => 'shBrushVb.js',
			'xml'        => 'shBrushXml.js',
			'html'       => 'shBrushXml.js',
			'xhtml'      => 'shBrushXml.js',
			'xslt'       => 'shBrushXml.js',
		);
		$this->opts = array(
			// directive => array(true, false, default, description) 
			'numbers' => array('', ':nogutter', true, 'Show line numbers'),
			'controls' => array('', ':nocontrols', true, 'Show clipboard controls above each code block'),
			'collapse' => array(':collapse', '', false, 
				'Collapse code blocks when first shown (this has no effect if clipboard controls are not shown)'),
			'columns' => array(':showcolumns', '', false, 
				'Show column numbers above each code block')
		);
		$settings = get_option('jm_coder');
		foreach ($this->opts as $k => $v) {
			if ($settings !== false && isset($settings[$k])) {
				$this->$k = $settings[$k];
			}
			else {
				$this->$k = $v[2];			
			}
		}
	}
 	
	
	/* ====== Configuration ====== */
	
	function admin_page()
	{
		if ('POST' == $_SERVER['REQUEST_METHOD']) {
			
			$settings = array();
			foreach ($this->opts as $k => $v) {
				$this->$k = $settings[$k] = $_POST[$k] ? true : false;
			}
			update_option('jm_coder', $settings);

			echo '<div id="coder-saved" class="updated fade-ffff00"">';
			echo '<p><strong>';
			_e('Options saved.');
			echo '</strong></p></div>';
		}

		require_once(dirname(__FILE__) . '/config.php');
	}
	
	
	/* ====== add_config_page ====== */

	/**
	 * Adds the configuration page to the submenu
	 */

	function add_config_page()
	{
		add_submenu_page('options-general.php', __('Source code'), __('Source code'), 
			'manage_options', 'coder', array(&$this, 'admin_page'));
	}

	
	
	/* ====== constructor ====== */

	function jm_coder()
	{
		$this->caeInit();
		$this->jsInit();
	}
}


$myCoder = new jm_coder();