<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Template_Token_Base {

	/**
	 * Base array instances container
	 *
	 * @var array
	 */
	static protected $instance = array();
	protected $replacers = array();
	protected $replacer_class_prefix = 'Billrun_Template_Token_Replacers_';
	protected $replacer_token_pattern = '/(\[{2}[\w\d]+::[\w\d]+\]{2})/';
	
	public function __construct($settings) {
		$tokens_categories = $this->getTokensCategories();
		$this->initTokensReplacers($tokens_categories);
	}

	/**
	 * Loose coupling of objects in the system
	 *
	 * @return mixed the bridge class
	 */
	static public function getInstance() {
		$args = func_get_args();

		$stamp = md5(serialize($args));
		if (isset(self::$instance[$stamp])) {
			return self::$instance[$stamp];
		}

		$called_class = get_called_class();

		if ($called_class && Billrun_Factory::config()->getConfigValue('TemplateTokens')) {
			$args = array_merge(Billrun_Factory::config()->getConfigValue($called_class)->toArray(), $args);
		}

		self::$instance[$stamp] = new $called_class($args);
		return self::$instance[$stamp];
	}
	
	/**
	 * Return avalible tokens array by category
	 * 
	 * @return Array
	 */
	public function getTokens() {
		$tokens = array();
		foreach ($this->replacers as $category => $replacer) {
			$tokens[$category] = $replacer->getAvailableTokens();
		}
		return $tokens;
	}

	/**
	 * Function to replace string tokens
	 * 
	 * @param String $string the string with tokens
	 * @param Array $params data for replaccers by categort
	 * @return String with replaced tokens
	 */
	public function replaceTokens($string, $params) {
		$string_tokens = $this->parseStringTokens($string);
		//get all unique tokens
		foreach ($string_tokens as $category => $tokens_match) {			
			$this->replacers[$category]->setData($params[$category]);
			foreach ($tokens_match['value'] as $key => $value) {
				$string_tokens[$category]['replace'][$key] = $this->replacers[$category]->replaceTokens($value);
			}
		}
		//replace tokens with values
		foreach ($string_tokens as $tokens_type) {
			foreach ($tokens_type['matche'] as $key => $matche) {
				$string = str_replace($matche, $tokens_type['replace'][$key], $string);
			}
		}
		return $string;
	}

	protected function getTokensCategories() {
		$conf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/TemplateTokens/conf.ini'));
		$tokens_categories = $conf->getConfigValue("templateTokens.enabled", array());
		return $tokens_categories;
	}

	protected function initTokensReplacers($tokens_categories) {
		if(!empty($tokens_categories) && is_array($tokens_categories)){
			foreach ($tokens_categories as $category) {
				if(!isset($this->replacers[$category])){
					$replacer_class = $this->replacer_class_prefix . ucfirst($category);
					$this->replacers[$category] = new $replacer_class();
				}
			}
		}
	}

	protected function parseStringTokens($string) {
		$tokens = array();
		$matches = array();
		preg_match_all($this->replacer_token_pattern, $string, $matches);
		if (!empty($matches[0]) && is_array($matches[0])) {
			foreach ($matches[0] as $matche) {
				$trimed_matche = trim($matche, "[]");
				$exploded_matche = explode("::", $trimed_matche);

				if (empty($tokens[$exploded_matche[0]])) {
					$tokens[$exploded_matche[0]] = array();
				}
				if (!in_array($exploded_matche[1], $tokens[$exploded_matche[0]]['value'])) {
					$tokens[$exploded_matche[0]]['value'][] = $exploded_matche[1];
					$tokens[$exploded_matche[0]]['matche'][] = $matche;
				}
			}
		}
		return $tokens;
	}

}
