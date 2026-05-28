<?php

/**
 * Zend Queue Adapter for RabbitMQ via the php-amqp extension.
 *
 * Requires: pecl install amqp
 *
 * Key design notes
 * ----------------
 * - Uses the *default exchange* (empty string) so every declared queue is
 *   automatically bound to it with the queue name as the routing key.
 * - Messages are published as *persistent* (delivery_mode = 2) and queues
 *   are declared as *durable*, so both survive a broker restart.
 * - deleteMessage() sends an AMQP ACK, which is the correct way to remove
 *   a message that has already been delivered. The delivery tag stored in
 *   $message->handle is used to identify the message to the broker.
 * - isExists() uses a passive declare on a *throw-away channel* so that
 *   the AMQP channel-level exception (404) does not poison the main channel.
 * - getQueues() is not supported natively by the AMQP protocol; the method
 *   returns locally-tracked queues and getCapabilities() marks it false so
 *   callers can guard with isSupported().
 */

require_once 'Zend/Queue/Adapter/AdapterAbstract.php';

class Zend_Queue_Adapter_Rabbitmq extends Zend_Queue_Adapter_AdapterAbstract
{
    const DEFAULT_HOST     = '127.0.0.1';
    const DEFAULT_PORT     = 5672;
    const DEFAULT_VHOST    = '/';
    const DEFAULT_LOGIN    = 'guest';
    const DEFAULT_PASSWORD = 'guest';

    /**
     * Name of the x-delayed-message exchange used for scheduled messages.
     * One exchange is shared across all queues on this connection.
     */
    const DELAYED_EXCHANGE = 'zend_queue.delayed';

    /** @var AMQPConnection */
    protected $_connection = null;

    /** @var AMQPChannel */
    protected $_channel = null;

    /**
     * Local cache of declared AMQPQueue objects, keyed by queue name.
     * @var AMQPQueue[]
     */
    protected $_amqpQueues = [];

    /**
     * Whether the delayed-message exchange has been declared this connection.
     * @var bool
     */
    protected $_delayedExchangeDeclared = false;

    /********************************************************************
     * Constructor / Destructor
     ********************************************************************/

    /**
     * @param array|Zend_Config $options  Expects a 'driverOptions' sub-array with:
     *   host, port, vhost, login, password
     * @param Zend_Queue|null $queue
     */
    public function __construct($options, Zend_Queue $queue = null)
    {
        if (!extension_loaded('amqp')) {
            require_once 'Zend/Queue/Exception.php';
            throw new Zend_Queue_Exception('The php-amqp extension is required for the RabbitMQ adapter.');
        }

        parent::__construct($options, $queue);

        $opts = &$this->_options['driverOptions'];
        $opts += [
            'host'     => self::DEFAULT_HOST,
            'port'     => self::DEFAULT_PORT,
            'vhost'    => self::DEFAULT_VHOST,
            'login'    => self::DEFAULT_LOGIN,
            'password' => self::DEFAULT_PASSWORD,
        ];

        $this->_connection = new AMQPConnection([
            'host'     => $opts['host'],
            'port'     => (int) $opts['port'],
            'vhost'    => $opts['vhost'],
            'login'    => $opts['login'],
            'password' => $opts['password'],
        ]);

        try {
            $this->_connection->connect();
        } catch (AMQPConnectionException $e) {
            require_once 'Zend/Queue/Exception.php';
            throw new Zend_Queue_Exception(
                'Failed to connect to RabbitMQ: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $this->_channel = new AMQPChannel($this->_connection);
    }

    public function __destruct()
    {
        if ($this->_connection instanceof AMQPConnection && $this->_connection->isConnected()) {
            $this->_connection->disconnect();
        }
    }

    /********************************************************************
     * Queue management functions
     ********************************************************************/

    /**
     * Check whether a queue already exists on the broker.
     *
     * A *passive* declare is used so the broker returns a 404 error (instead
     * of creating the queue) when it does not exist. Because an AMQP
     * channel-level error closes the channel, we open a dedicated short-lived
     * channel for this check and discard it afterwards.
     *
     * @param string $name
     * @return boolean
     */
    public function isExists($name)
    {
        $probeChannel = new AMQPChannel($this->_connection);
        try {
            $q = new AMQPQueue($probeChannel);
            $q->setName($name);
            $q->setFlags(AMQP_PASSIVE);
            $q->declareQueue();
            return true;
        } catch (AMQPQueueException $e) {
            // 404 NOT_FOUND — queue does not exist
            return false;
        } finally {
            // The channel may already be closed by the broker after a 404,
            // so suppress any errors during cleanup.
            try { unset($probeChannel); } catch (Exception $e) {}
        }
    }

    /**
     * Declare a durable queue on the broker.
     *
     * @param string   $name
     * @param int|null $timeout  Not used by RabbitMQ; accepted for interface compliance.
     * @return boolean  false if the queue already existed, true if newly created.
     */
    public function create($name, $timeout = null)
    {
        if ($this->isExists($name)) {
            return false;
        }
        $this->_declareQueue($name);
        return true;
    }

    /**
     * Delete a queue and all of its messages from the broker.
     *
     * @param string $name
     * @return boolean
     */
    public function delete($name)
    {
        try {
            $q = new AMQPQueue($this->_channel);
            $q->setName($name);
            $q->delete();
            unset($this->_amqpQueues[$name]);
            return true;
        } catch (AMQPQueueException $e) {
            return false;
        }
    }

    /**
     * Return locally-tracked queue names.
     *
     * Listing all queues on a broker is not possible through the AMQP protocol
     * itself; it requires the RabbitMQ Management HTTP API. This method
     * therefore only reflects queues declared within the current connection.
     * Use isSupported('getQueues') to guard against this limitation.
     *
     * @return string[]
     */
    public function getQueues()
    {
        return array_keys($this->_amqpQueues);
    }

    /**
     * Return the number of messages ready (not yet delivered) in the queue.
     *
     * Re-declaring an existing queue with identical arguments is idempotent
     * in AMQP and the server responds with the current message/consumer counts.
     *
     * @param Zend_Queue|null $queue
     * @return integer
     */
    public function count(Zend_Queue $queue = null)
    {
        if ($queue === null) {
            $queue = $this->_queue;
        }
        // declareQueue() returns message count when the queue already exists.
        return (int) $this->_declareQueue($queue->getName());
    }

    /********************************************************************
     * Message management functions
     ********************************************************************/

    /**
     * Publish a message to the queue.
     *
     * If $message is an array containing a 'schedule' key (a Unix timestamp),
     * the message will be held by the broker until that time arrives, using
     * the rabbitmq-delayed-message-exchange plugin.
     * The 'schedule' key is stripped from the body before publishing,
     * matching the behaviour of the MongoDB adapter.
     *
     * Requires the rabbitmq-delayed-message-exchange plugin to be installed
     * on the broker for scheduled messages. Non-scheduled messages work with
     * a vanilla RabbitMQ installation.
     *
     * @param string|array    $message  Plain string, or array with optional 'schedule' (Unix timestamp).
     * @param Zend_Queue|null $queue
     * @return Zend_Queue_Message
     */
    public function send($message, Zend_Queue $queue = null)
    {
        if ($queue === null) {
            $queue = $this->_queue;
        }

        $this->_declareQueue($queue->getName());

        $scheduleAt  = null;
        $messageBody = $message;

        if (is_array($message) && isset($message['schedule'])) {
            $scheduleAt  = (int) $message['schedule'];
            $messageBody = $message;
            unset($messageBody['schedule']);
            $messageBody = serialize($messageBody); // keep array payload intact
        }

        $attributes = [
            'delivery_mode' => 2, // persistent
            'content_type'  => 'text/plain',
        ];

        if ($scheduleAt !== null) {
            // x-delay is in milliseconds; calculate from now to the scheduled Unix timestamp.
            $delayMs = max(0, ($scheduleAt - time()) * 1000);
            $attributes['headers'] = ['x-delay' => $delayMs];

            $exchange = $this->_declareDelayedExchange($queue->getName());
        } else {
            $exchange = new AMQPExchange($this->_channel); // default exchange
        }

        $exchange->publish(
            (string) $messageBody,
            $queue->getName(), // routing key
            AMQP_NOPARAM,
            $attributes
        );

        $data = [
            'message_id' => md5(uniqid(rand(), true)),
            'handle'     => null,
            'body'       => $messageBody,
            'md5'        => md5((string) $messageBody),
            'schedule'   => $scheduleAt,
        ];

        $options = ['queue' => $queue, 'data' => $data];

        $classname = $queue->getMessageClass();
        if (!class_exists($classname)) {
            require_once 'Zend/Loader.php';
            Zend_Loader::loadClass($classname);
        }
        return new $classname($options);
    }

    /**
     * Retrieve up to $maxMessages from the queue without blocking.
     *
     * Each returned message's 'handle' property holds the AMQP delivery tag,
     * which must be passed back to deleteMessage() to ACK the message.
     *
     * @param int|null        $maxMessages
     * @param int|null        $timeout  Accepted for interface compliance; not used (non-blocking get).
     * @param Zend_Queue|null $queue
     * @return Zend_Queue_Message_Iterator
     */
    public function receive($maxMessages = null, $timeout = null, Zend_Queue $queue = null)
    {
        if ($maxMessages === null || !is_int($maxMessages) || $maxMessages < 1) {
            $maxMessages = 1;
        }
        if ($queue === null) {
            $queue = $this->_queue;
        }

        $amqpQueue = $this->_declareQueue($queue->getName());
        $msgs      = [];

        for ($i = 0; $i < $maxMessages; $i++) {
            $envelope = $amqpQueue->get(AMQP_NOPARAM); // non-blocking
            if (!$envelope instanceof AMQPEnvelope) {
                break; // no more messages available right now
            }
            $msgs[] = [
                'message_id' => $envelope->getMessageId() ?: md5(uniqid(rand(), true)),
                'handle'     => $envelope->getDeliveryTag(), // needed for ACK
                'body'       => $envelope->getBody(),
                'md5'        => md5($envelope->getBody()),
            ];
        }

        $options = [
            'queue'        => $queue,
            'data'         => $msgs,
            'messageClass' => $queue->getMessageClass(),
        ];

        $classname = $queue->getMessageSetClass();
        if (!class_exists($classname)) {
            require_once 'Zend/Loader.php';
            Zend_Loader::loadClass($classname);
        }
        return new $classname($options);
    }

    /**
     * Acknowledge (permanently remove) a previously received message.
     *
     * In RabbitMQ, a message delivered with AMQP_NOPARAM (require-ack mode)
     * stays in an "unacknowledged" state until the consumer either ACKs it
     * (deleteMessage) or the channel closes, at which point the broker
     * re-queues it.
     *
     * @param Zend_Queue_Message $message  Must have a non-null 'handle' (delivery tag).
     * @return boolean
     */
    public function deleteMessage(Zend_Queue_Message $message)
    {
        if (empty($message->handle)) {
            return false;
        }
        $this->_channel->ack((int) $message->handle);
        return true;
    }

    /********************************************************************
     * Capabilities
     ********************************************************************/

    /**
     * @return array
     */
    public function getCapabilities()
    {
        return [
            'create'        => true,
            'delete'        => true,
            'send'          => true,
            'receive'       => true,
            'deleteMessage' => true,
            'getQueues'     => false, // requires Management HTTP API; not available via AMQP
            'count'         => true,
            'isExists'      => true,
        ];
    }

    /********************************************************************
     * Internal helpers
     ********************************************************************/

    /**
     * Declare a durable queue (idempotent) and cache the AMQPQueue object.
     *
     * @param string $name
     * @return AMQPQueue
     */
    protected function _declareQueue($name)
    {
        if (!isset($this->_amqpQueues[$name])) {
            $q = new AMQPQueue($this->_channel);
            $q->setName($name);
            $q->setFlags(AMQP_DURABLE);
            $q->declareQueue();
            $this->_amqpQueues[$name] = $q;
        }
        return $this->_amqpQueues[$name];
    }

    /**
     * Declare the shared x-delayed-message exchange (once per connection)
     * and bind the target queue to it.
     *
     * The exchange type 'x-delayed-message' is provided by the
     * rabbitmq-delayed-message-exchange plugin. The inner routing type
     * is 'direct', so each queue is bound with its own name as the routing key.
     *
     * Plugin: https://github.com/rabbitmq/rabbitmq-delayed-message-exchange
     *
     * @param string $queueName  The queue to bind to the delayed exchange.
     * @return AMQPExchange
     * @throws Zend_Queue_Exception if the plugin is not installed on the broker.
     */
    protected function _declareDelayedExchange($queueName)
    {
        $exchange = new AMQPExchange($this->_channel);
        $exchange->setName(self::DELAYED_EXCHANGE);
        $exchange->setType('x-delayed-message');
        $exchange->setFlags(AMQP_DURABLE);
        $exchange->setArguments(['x-delayed-type' => 'direct']);

        if (!$this->_delayedExchangeDeclared) {
            try {
                $exchange->declareExchange();
            } catch (AMQPExchangeException $e) {
                require_once 'Zend/Queue/Exception.php';
                throw new Zend_Queue_Exception(
                    'Failed to declare the delayed-message exchange. ' .
                    'Ensure the rabbitmq-delayed-message-exchange plugin is enabled. ' .
                    'Original error: ' . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
            $this->_delayedExchangeDeclared = true;
        }

        // Bind the queue to the exchange so delayed messages are routed correctly.
        $q = $this->_declareQueue($queueName);
        $q->bind(self::DELAYED_EXCHANGE, $queueName);

        return $exchange;
    }
}