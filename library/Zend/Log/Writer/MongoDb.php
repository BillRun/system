<?php
/**
 * @category Zend
 * @package Zend_Log
 * @subpackage Writer
 */

/**
 * <b>Example 1:</b>
 *
 * <code>
 * $logger = Zend_Log::factory(
 *     array('timestampFormat' => 'Y-m-d',
 *     array('writerName' => 'MongoDb',
 *     'writerParams' => array(
 *         'server' => 'mongodb://somehost.mongolab.com:27017',
 *         'collection' => 'logging', 'database' => 'zend_log',
 *         'options' => array('username' => 'zircote-dev',
 *             'password' => 'somepassword', 'connect' => true, 'timeout' => 200,
 *             'replicaSet' => 'repset1', 'db' => 'zend_log'
 *         )
 *     ),
 *     'formatterName' => 'Simple',
 *     'formatterParams' => array(
 *         'format' => '%timestamp%: %message% -- %info%'),
 *         'filterName' => 'Priority',
 *         'filterParams' => array('priority' => Zend_Log::WARN)),
 *         array('writerName' => 'Firebug', 'filterName' => 'Priority',
 *             'filterParams' => array('priority' => Zend_Log::INFO))
 *     )
 * );
 * $logger->crit(__METHOD__);
 * </code>
 *
 * <b>Example 2:</b>
 *
 * <code>
 * $config = array('server' => 'mongodb://somehost.mongolab.com:27017',
 *     'collection' => 'logging', 'database' => 'zend_log',
 *     'options' => array('username' => 'zircote-dev',
 *     'password' => 'somepassword', 'connect' => true, 'timeout' => 200,
 *     'replicaSet' => 'repset1', 'db' => 'zend_log')
 * );
 * $log = new Zend_log();
 * $log->addWriter(Zend_Log_Writer_MongoDb::factory($config));
 * $log->info('this is a test ' . __METHOD__);
 * </code>
 *
 * <b>Example 3:</b>
 *
 * <code>
 * $config = array('collection' => 'log','database' => 'pincrowd');
 * $writer = Zend_Log_Writer_MongoDb::factory($config);
 * $log = new Zend_log();
 * $log->addWriter($writer);
 * $log->info('this is a test');
 * </code>
 *
 * <b>Example 4:</b>
 *
 * <code>
 * $mongo = new MongoDb();
 * $collection = $mongo->selectDB('logging')
 *     ->selectCollection('logCollection');
 * $log = new Zend_log();
 * $writer = new Zend_Log_Writer_MongoDb($collection);
 * $log->addWriter($writer);
 * $log->err(__METHOD__);
 * </code>
 *
 * <b>Reading Logs from a Tailable Cursor:</b>
 * $mongo = new Mongo();
 * $db = $mongo->selectDB('logging');
 * $collection = $db->selectCollection('logCollection');
 * $cursor = $collection->find()->tailable(true);
 * while (true) {
 *     if ($cursor->hasNext()) {
 *         $doc = $cursor->getNext();
 *         echo date(DATE_ISO8601, $doc['timestamp']->sec), ' ',$doc['priorityName'],' ', $doc['message'], PHP_EOL;
 *     } else {
 *         usleep(100);
 *     }
 * }
 * </code>
 *
 * <bZend_Application_Resource_Log</b>
 *
 * <code>
 * ;;; application.ini ;;;
 * resources.log.mongo.writerName = "MongoDb"
 * resources.log.mongo.writerParams.database = "pincrowd"
 * resources.log.mongo.writerParams.collection = "logging"
 * resources.log.mongo.writerParams.documentMap.timestamp = 'timestamp'
 * resources.log.mongo.writerParams.documentMap.message = 'message'
 * resources.log.mongo.writerParams.documentMap.priority = 'priority'
 * resources.log.mongo.writerParams.documentMap.priorityName = 'priorityName'
 * resources.log.mongo.writerParams.documentMap.hostname = 'hostname'
 * resources.log.mongo.filterName = "Priority"
 * resources.log.mongo.filterParams.priority = 5
 *
 * <?php
 * if($bootstrap->hasResource('log')){
 *     $log = $bootstrap->getResource('log');
 *     $log->info('log me');
 * }
 * </code>
 * @category Zend
 * @package Zend_Log
 * @subpackage Writer
 *
 */
class Zend_Log_Writer_MongoDb extends Zend_Log_Writer_Abstract
{
    /**
     *
     *
     * @var MongoDB\Collection
     */
    protected $_collection;
    /**
     * Defines the mapping of data to the collection members.
     *
     * <b>Zend_Config_Ini Example:</b>
     *
     * <code>
     * resources.log.mongo.writerParams.documentMap.timestamp = 'timestamp'
     * resources.log.mongo.writerParams.documentMap.message = 'message'
     * resources.log.mongo.writerParams.documentMap.priority = 'priority'
     * resources.log.mongo.writerParams.documentMap.priorityName = 'priorityName'
     * resources.log.mongo.writerParams.documentMap.hostname = 'hostname'
     * </code>
     * @var array
     */
    protected $_documentMap = array(
        'timestamp' => 'timestamp',
        'message' => 'message',
        'priority' => 'priority',
        'priorityName' => 'priorityName',
        'hostname' => 'hostname'
    );
    /**
     * Originating hostname of the log entry.
     *
     * @var string
     */
    protected $_hostname;
    /**
     *
     *
     * @param MongoDB\Collection $collection
     * @param array $documentMap
     */
    public function __construct(MongoDB\Collection $collection, $documentMap = null)
    {
        if (!extension_loaded('Mongo')) {
            Zend_Cache::throwException("Cannot use Mongo storage because the ".
            "'Mongo' extension is not loaded in the current PHP environment");
        }
        if(!is_array($documentMap)){
            $documentMap = array();
        }
        $this->_collection = $collection;
        $this->_documentMap = array_merge($this->_documentMap, $documentMap);
        $this->_setHostname();
    }
    /**
     * (non-PHPdoc)
     * @see Zend_Log_Writer_Abstract::_write()
     */
    protected function _write ($event)
    {
        $event['hostname'] = $this->_hostname;
        if ($this->_collection === null) {
            throw new Zend_Log_Exception('MongoDb object is null');
        }
        $event['timestamp'] = new MongoDate(strtotime($event['timestamp']));
        if ($this->_documentMap === null) {
            $dataToInsert = $event;
        } else {
            $dataToInsert = array();
            foreach ($this->_documentMap as $columnName => $fieldKey) {
                $dataToInsert[$columnName] = $event[$fieldKey];
            }
        }
        $this->_collection->insert($dataToInsert);
    }
    /**
     *
     * @param array $config
     * @return Zend_Log_Writer_MongoDb
     */
    public static function factory ($config)
    {
        $config = self::_parseConfig($config);

        if (isset($config['columnmap'])) {
            $config['columnMap'] = $config['columnmap'];
        }
        $config = array_merge(
            array('collection'  => null,
            'documentMap' => null),
            $config
        );
        if (isset($config['documentmap'])) {
            $config['documentMap'] = $config['documentmap'];
        }
        if(!$config['collection'] instanceof MongoDB\Collection){
            $config['collection'] = self::_createMongoCollection($config);
        }
        return new self(
            $config['collection'],
            $config['documentMap']
        );
    }
    /**
     * Create the MongoDB\Collection Object.
     *
     * @param array $config
     * @return MongoDB\Collection
     */
    static protected function _createMongoCollection($config)
    {
        if(!isset($config['server'])){
            $server  = "mongodb://localhost:27017";
        } else {
            $server = $config['server'];
        }
        if(!isset($config['options']) || !is_array($config['options'])){
            $options = array();
        } else {
            $options = $config['options'];
        }
		if (isset($config['database'])) {
			$options['db'] = $config['database'];
		}
        $mongo = new MongoDB\Client($server, $options);
        return $mongo->selectDatabase($config['database'])
            ->selectCollection($config['collection']);
    }
    /**
     * Determine the hostname of the server executing the code. This allows for
     * demarcation of entries in a cluster.
     */
    protected function _setHostname()
    {
        if(!$this->_hostname){
            $this->_hostname = php_uname('n');
        }
    }
}