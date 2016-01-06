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
		'number'=>'NumberValidator',
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

	protected function addError($attribute,$message,$code ,$index=-1)
	{
    $this->valid = false ;  
    if($index>=0) 
		   $this->errors['attributes'][$attribute][$index][]  = array ("message" => $message , "error_code" => $code );
     else 
       $this->errors['attributes'][$attribute][]  = array ("message" => $message , "error_code" => $code );
	}

  public function RequiredValidator ($attribute , $value , $validationOptions =array("trim"=>false),$index=-1) { 
  	$code = "required" ;
  	$trim = $validationOptions["trim"];
  	if(isset($validationOptions["message"])) {
  		$message = $validationOptions['message'] ;
  	} else {
  		$message = $attribute . " is required" ;
  	}

  	if($this->isEmpty($value,$trim)) {
  		$this->addError($attribute,$message,$code,$index) ;
  		return false ;
  	}
  	return true ;
  }


public function UniqueValidator($attribute , $value , $validationOptions =array("trim"=>false),$index=-1) { 
  
    $code = "unique" ;
    if(strlen(trim("$value")) == 0  ||  !isset($validationOptions["collection"])) {
        return true;      
    }


    $collection = Billrun_Factory::db()->getCollection($validationOptions["collection"]);

    if (!($collection instanceof Mongodloid_Collection)) {
      return true;
    }


    if(isset($validationOptions["message"])) {
      $message = $validationOptions['message'] ;
    } else {
      $message = $attribute .  " with value : ". $value ." has already been taken" ;
    }

    $checkUniqueQuery = array($attribute => $value);
    if($MongoID =  $validationOptions["objectRef"]["_id"])  { 

      $checkUniqueQuery =  array_merge($checkUniqueQuery,array( "_id" => array('$ne' => (string)$MongoID))) ;
    }
    
    Billrun_Factory::log("checkUniqueQuery : " .print_r($checkUniqueQuery) , Zend_Log::DEBUG);

    $cursor =  $collection->find($checkUniqueQuery,array())  ; 

    if($cursor->count())   {
      $this->addError($attribute,$message,$code,$index) ;
      return false; 
    }
    return true ;
  }


	public function NumberValidator ($attribute , $value  ,$validationOptions = array(),$index=-1) { 
  	$code = "number" ;
  	
    if(strlen(trim("$value")) == 0 ) {
        return true;      
    }
  	if(isset($validationOptions["message"])) {
  		$message =  $validationOptions['message'] ;
  	} else {
  		$message = $attribute . " must be an number" ;
  	}

   	if(!preg_match($this->numberPattern,"$value"))	{
				$this->addError($attribute,$message,$code,$index) ;
			 return false; 
			}

  		return true;
  }


public function LengthValidator ($attribute , $value  ,$validationOptions = array("min" => null , "max" => null ),$index=-1) { 
  	$length=strlen($value);

  	$code="length" ;
    $status = true; 

		if($validationOptions["min"] !==null && $length<$validationOptions["min"])
		{
			$message= $attribute . " is too short (minimum is " . $validationOptions["min"] . " characters)')";
			$this->addError($attribute,$message,$code,$index) ;
      $status = false ;
		}

		if($validationOptions["max"] !==null && $length>$validationOptions["max"])
		{
			$message= $attribute ." is too long (maximum is " . $validationOptions["max"] . " characters)')";
			$this->addError($attribute,$message,$code,$index) ;
      $status = false ;
		}
    return $status ;

  }

	

    public function IntegerValidator ($attribute , $value , $validationOptions = array() ,$index=-1) { 

  	$code = "integer" ;
    if(strlen(trim("$value")) == 0 ) 
      return  true ;

  	if(isset($validationOptions["message"])) {
  		$message = $validationOptions['message'] ;
  	} else { 
  		$message = $attribute . " must be an integer" ;
  	}
  	if( !$this->NumberValidator($attribute,$value ,$validationOptions = array("message" => $message),$index)) {
  			return true;
  	};
  	if(!preg_match($this->integerPattern,"$value"))	{
				$this->addError($attribute,$message,$code,$index) ;
				return false;
		}
  	return true ;
  }

	 public function validate($object,$collection) {

      /*  get collection rules tree    */
      $val = $this->getKeyVal(array($this->validations,$collection));
      if(!$this->getKeyVal(array($this->validations,$collection))) { 
        $this->isValid = true ; 
        return $this ;
      }
      /* loop over the collection attributes */

      foreach($object as $attr => $attrValue) {
        

        /* the attribute validation rules */
        $attrRules= $this->getKeyVal(array($this->validations,$collection,$attr)) ;  

        if($attrRules == null) continue ;
        
         
        $attrType = $this->getKeyVal(array($attrRules,"is"),"scalar") ;

        if(isset($attrRules["is"])) { 
           unset($attrRules["is"]);
        }  
        

        foreach($attrRules as $check => $checkOptions ) { 
          //skip undefined validation test

          if(!is_array($checkOptions)) {
            $checkOptions = array() ;
          }

          if($check == "unique") {
            if(!isset($checkOptions["collection"])) { 
              $checkOptions = array_merge(array("collection" => $collection) ,$checkOptions);
            }
          }
         
          $checkOptions["objectRef"] = $object ;
          if(!(isset(self::$validatorsFunctions[$check]))) {
              Billrun_Factory::log("undefined check function  => $check (please implement)" , Zend_Log::DEBUG);
              continue ;
          }
        

        if($attrType == "array") { 
          $fn = array('self', self::$validatorsFunctions[$check] );
          $valIndex=0;  
          foreach($attrValue as $scalarVal) { 
             call_user_func( $fn,$attr,$scalarVal,$checkOptions,$valIndex);
             $valIndex++;
          }
           
        }

        if($attrType == "scalar" ) { 
          $fn = array('self', self::$validatorsFunctions[$check] );
          call_user_func( $fn,$attr,$attrValue,$checkOptions);          
        }     
      }
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

  public function getKeyVal($array=array(),$default=null) {
      $where =  array_shift($array)  ;
      $stepinto = $where;

      while ($key = array_shift($array)) { 
         if(!isset($stepinto[$key])) {
            return $default ;
         } else { 
             $stepinto = $stepinto[$key]; 
         }
      }

      return $stepinto;
  }
 } 