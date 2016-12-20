<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Template_Token_Base extends Billrun_Base {
	
	/**
	 * Base array instances container
	 *
	 * @var array
	 */
	static protected $instance = array();
	
	protected $conf = '';
	
	protected $tokensCategories = '';
	
		
	public function __construct($settings) {
		$this->conf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/TemplateTokens/conf.ini'));
		$this->tokensCategories = $this->conf->getConfigValue("templateTokens.enabled", array());
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
	
	public function getTokensCategories() {
		return  $this->tokensCategories;
	}
	
	public function getTokens() {
		$tokens = array();
		$tokens_categories = $this->getTokensCategories();
		foreach ($tokens_categories as $token_class_name) {
			if(!isset($tokens[$token_class_name])){
				$tokens[$token_class_name] = new $token_class_name();
			}
			$tokens = array_merge($tokens, $tokens[$token_class_name]->getAvailableTokens());
		}
		return $tokens;
	}
	
	public function replaceTokens($string, $params){
		$class_prfix = 'Billrun_Template_Token_Tokens_';
		$tokens = $this->parseStringTokens($string);
		foreach ($tokens as $type => $tokens_match) {
			$replacerClass = $class_prfix . ucfirst($type);
			$replacer = new $replacerClass($params[$type]);
			foreach ($tokens_match['value'] as $key => $value) {
				$tokens[$type]['replace'][$key] = $replacer->replaceTokens($value);
			}
		}
		
		foreach ($tokens as $tokens_type) {
			foreach ($tokens_type['matche'] as $key => $matche) {
				$string = str_replace($matche, $tokens_type['replace'][$key], $string);
			}
			
		}
		return $string;
	}
	
	protected function parseStringTokens($string){
		$tokens = array();
		$matches = array();
		preg_match_all('/(\[{2}[\w\d]+::[\w\d]+\]{2})/', $string, $matches);
		if(!empty($matches[0]) && is_array($matches[0])){		
			foreach ($matches[0] as $matche) {
				$trimed_matche = ltrim($matche, "[[");
				$trimed_matche = rtrim($trimed_matche, "]]");
				$exploded_matche = explode("::", $trimed_matche);
				
				if( empty($tokens[$exploded_matche[0]]) ){
					$tokens[$exploded_matche[0]] = array();
				}
				if(!in_array($exploded_matche[1], $tokens[$exploded_matche[0]]['value'])){
					$tokens[$exploded_matche[0]]['value'][] = $exploded_matche[1];
					$tokens[$exploded_matche[0]]['matche'][] = $matche;
				}

			}
		}
		return $tokens;
	}
}

	
