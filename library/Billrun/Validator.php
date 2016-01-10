<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Validator class
 *
*/


class Billrun_Validator {
	
	public $integerPattern='/^\s*[+-]?\d+\s*$/';
	public $numberPattern='/^\s*[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?\s*$/';
  

	public static $validatorsFunctions=array(
		'required'=>'RequiredValidator',
		'filter'=>'FilterValidator',
		'match'=>'RegularExpressionValidator',
		'email'=>'EmailValidator',
		'url'=>'UrlValidator',
		'unique'=>'UniqueValidator',
		'compare'=>'CompareValidator',
		'length'=>'LengthValidator',
		'in'=>'RangeValidator',
		'numerical'=>'NumberValidator',
    'integer'=>'IntegerValidator', 
		'captcha'=>'CaptchaValidator',
		'type'=>'TypeValidator',
		'default'=>'DefaultValueValidator',
		'boolean'=>'BooleanValidator',
		'date'=>'DateValidator',
	);
	
	protected $options;
	protected $rules;
	protected $errors;
	protected $params;
  protected $isValid;

	public function __construct(array $params = array()) {
		
		$this->params = $params; 
		$this->errors = array("attributes">array(),"global"=>array());
		$this->options = array();
    $this->isValid = true ;
		$this->validations = 	Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/validation.ini'))->toArray();
//    Billrun_Factory::log("validation.ini " . print_r($this->validations ,true), Zend_Log::DEBUG);


	}

 	protected function isEmpty($value,$trim=false)
	{
		return $value===null || $value===array() || $value==='' || $trim && is_scalar($value) && trim($value)==='';
	}

	protected function addError($attribute,$message,$code )
	{
    $this->valid = false ; 
		$this->errors['attributes'][$attribute][]  = array ("message" => $message , "error_code" => $code );
	}

  public function RequiredValidator ($attribute , $value , $extra =array("trim"=>false)) { 
  	$code = "required" ;
  	$trim = $extra['trim'];
  	if(isset($extra["message"])) {
  		$message = $extra['message'] ;
  	} else {
  		$message = $attribute . " is required" ;
  	}

  	if($this->isEmpty($value,$trim)) {
  		$this->addError($attribute,$message,$code) ;
  		return false ;
  	}
  	return true ;
  }


	public function NumberValidator ($attribute , $value  ,$extra = array()) { 
  	$code = "number" ;
  	
  	if(isset($extra["message"])) {
  		$message =  $extra['message'] ;
  	} else {
  		$message = $attribute . " must be an number" ;
  	}

  	if( !$this->RequiredValidator($attribute,$value ,$extra = array("message" => $message)) ) {
  			return false;
  	};

  	if(!preg_match($this->numberPattern,"$value"))	{
				$this->addError($attribute,$message,$code) ;
				$status = false; 
			}

  		return true;
  }


public function LengthValidator ($attribute , $value  ,$extra = array("min" => null , "max" => null )) { 
  	$length=strlen($value);

  	$code="length" ;

		if($extra["min"] !==null && $length<$extra["min"])
		{
			$message=" is too short (minimum is " . $extra["min"] . " characters)')";
			$this->addError($attribute,$message,$code) ;
		}

		if($extra["max"] !==null && $length<$extra["max"])
		{
			$message=" is too long (maximum is " . $extra["max"] . " characters)')";
			$this->addError($attribute,$message,$code) ;
		}
  }

	

    public function IntegerValidator ($attribute , $value , $extra = array() ) { 
  	$code = "integer" ;
  	
    Billrun_Factory::log("in IntegerValidator :$attribute , $value " . print_r($val ,true), Zend_Log::DEBUG);   

  	if(isset($extra["message"])) {
  		$message = $$extra['message'] ;
  	} else { 
  		$message = $attribute . " must be an integer" ;
  	}
  	if( !$this->NumberValidator($attribute,$value ,$extra = array("message" => $message)) ) {
  			return false;
  	};
  	if(!preg_match($this->integerPattern,"$value"))	{
				$this->addError($attribute,$message,$code) ;
				return false;
		}
  	return true ;
  }

	 public function validate($params,$collection) {
   
    $val = $this->getKeyVal(array($this->validations,$collection));
    //Billrun_Factory::log("getKeyVal $collection" . print_r($val ,true), Zend_Log::DEBUG);   
    if(!$this->getKeyVal(array($this->validations,$collection))) { 
      $this->isValid = true ; 
      return $this ;
    }
  

    foreach($params as $attr => $val) {
      
      $keyval= $this->getKeyVal(array($this->validations,$collection,$attr)) ;  
         if(!($keyval && isset($keyval["check"]) && isset(self::$validatorsFunctions[$keyval["check"]]))) {
         //  Billrun_Factory::log("check function  => $attr" . print_r($keyval["check"] ,true), Zend_Log::DEBUG);
         continue ;
      }

     $extra = $keyval  ; 
     unset($extra["check"]);

     $fn = array('self', self::$validatorsFunctions[$keyval["check"]] );
     call_user_func( $fn,$attr,$val,$extra);

    }
   
   return $this ;
  }

  public function getOptions() {
    return $this->options;
  }
  
  public function getRules() {
    return $this->rules;
  }

  public function getErrors() {
    return $this->errors;
  }
  
  public function getValidations() {
    return $this->validations;
  }
  public function isValid() {
    return $this->isValid;
  }

  public function getKeyVal($array=array()) {
      $where =  array_shift($array)  ;
      $stepinto = $where;

      while ($key = array_shift($array)) { 
         if(!isset($stepinto[$key])) {
            return null ;
         } else { 
             $stepinto = $stepinto[$key]; 
         }
      }

      return $stepinto;
  }
 } 