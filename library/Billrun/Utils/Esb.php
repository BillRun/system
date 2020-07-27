<?php


class Billrun_Utils_Esb  {
    protected $stompClient = null;
    protected $queueConfig = [];

    function __construct($options= array()) {
		$queue_config = $options['queue_config'] ?? array();
        $this->queueConfig = array_merge(Billrun_Factory::config()->getConfigValue('esb.queue_config', array()), $queue_config);
        $host = $this->queueConfig['host'] ?? '';
		$port = $this->queueConfig['port'] ?? '';
		$user = $this->queueConfig['user'] ?? '';
		$pass = $this->queueConfig['pass'] ?? '';
		try{
		   Billrun_Factory::log()->log("Connecting to Message Broker", Zend_Log::INFO);
           $this->stompClient = new Stomp('tcp://'. $host .":" . $port, $user, $pass);
        } catch(Exception $ex){
            Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
        }
    }

    /**
     * Send a message to the ESB on a given queue.
     */
		public function sendMsg($msg, $queueName, $headers = []) {
		if(!isset($this->stompClient)){
			return FALSE;
		}
        try {
			Billrun_Factory::log()->log("Sending message", Zend_Log::INFO);
	        return $this->stompClient->send($queueName, $msg, $headers);
        } catch (Exception $e) {
            Billrun_Factory::log('Esb send Error : '. $e->getMessage(), Zend_Log::CRIT);
            return FALSE;
        }
    }

    /**
     * Get Messages  from the ESB for a given queue.
     */
    public function getMsg($queueName, $waitTime = 86400000,$ack = TRUE) {
        if(!isset($this->stompClient)){
			return FALSE;
		}
		do {
        $starttime =  microtime(true);
			$this->stompClient->setReadTimeout($waitTime/1000);
			try {
				if($this->stompClient->hasFrame()) {
					$esbFrame = $this->stompClient->readFrame();
					$inQname = $this->getActionFromMsgHeaders($esbFrame);
					if($inQname = $queueName) {
						if($ack)  { $this->stompClient->ack($esbFrame); }

						return $esbFrame->body;
					}
				}
			} catch (Exception $e) {
				Billrun_Factory::log('Esb Recieve Error : '. $e->getMessage(), Zend_Log::CRIT);
				return FALSE;
			}
			$waitTime -= microtime(true) - $starttime;
        } while ($waitTime >= 0);
        return FALSE;
    }

    /**
     * Regster to given queues on the ESB
     */
    public function subscribeToQueues($queues, $headers = array()) {
        if(!isset($this->stompClient)){
			return FALSE;
		}
		foreach($queues as $qname) {
            $this->stompClient->subscribe($qname, $headers);
        }	
    }

    /**
	 * Get the queue name from a received  message header 
	 * @param type $esbFrame 
	 * @return type
	 */
	protected function getActionFromMsgHeaders($esbFrame) {
		return preg_replace('/(\/queue\/|\/'.str_replace('/','\/',$this->queueConfig['queue_prefix']).'\/|\/in|\/)/','',$esbFrame->headers['destination']);
	}
}