<?php
/**
 * Stakhanovist
 *
 * @link        https://github.com/stakhanovist/queue
 * @copyright   Copyright (c) 2015, Stakhanovist
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */
namespace Stakhanovist\Queue\Adapter\MongoDb;

use MongoCollection;
use MongoDB;
use MongoId;
use Stakhanovist\Queue\Adapter\AbstractAdapter;
use Stakhanovist\Queue\Adapter\Capabilities\CountMessagesCapableInterface;
use Stakhanovist\Queue\Exception;
use Stakhanovist\Queue\Message\MessageIterator;
use Stakhanovist\Queue\Parameter\ReceiveParametersInterface;
use Stakhanovist\Queue\Parameter\SendParametersInterface;
use Stakhanovist\Queue\QueueInterface;
use Zend\Stdlib\MessageInterface;

/**
 * Class AbstractMongo
 */
abstract class AbstractMongo extends AbstractAdapter implements CountMessagesCapableInterface
{
    const KEY_HANDLE = 'h';
    const KEY_CLASS = 't';
    const KEY_CONTENT = 'c';
    const KEY_METADATA = 'm';

    /**
     * Internal array of queues to save on lookups
     *
     * @var array
     */
    protected $queues = [];

    /**
     * @var \MongoDB
     */
    protected $mongoDb;

    /**
     * Constructor.
     *
     * $options is an array of key/value pairs or an instance of Traversable
     * containing configuration options.
     *
     * @param  array|\Traversable $options An array having configuration data
     * @throws Exception\InvalidArgumentException
     * @throws Exception\ExtensionNotLoadedException
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('mongo')) {
            throw new Exception\ExtensionNotLoadedException('Mongo extension is not loaded');
        }
        parent::__construct($options);
    }

    /**
     * List avaliable params for receiveMessages()
     *
     * @return array
     */
    public function getAvailableReceiveParams()
    {
        return [
            ReceiveParametersInterface::CLASS_FILTER,
        ];
    }

    /**
     * Ensure connection
     *
     * @return bool
     */
    public function connect()
    {
        $adapterOptions = $this->getOptions();

        if (isset($adapterOptions['mongoDb']) && $adapterOptions['mongoDb'] instanceof MongoDB) {
            $this->mongoDb = $adapterOptions['mongoDb'];
            return true;
        }

        $mongoClass = (version_compare(phpversion('mongo'), '1.3.0', '<')) ? 'Mongo' : 'MongoClient';
        $driverOptions = $adapterOptions['driverOptions'];

        $dsn = isset($driverOptions['dsn']) ?
            $driverOptions['dsn'] :
            'mongodb://' . ini_get('mongo.default_host') . ':' . ini_get('mongo.default_port');

        $db = null;
        if (isset($driverOptions['db'])) {
            $db = $driverOptions['db'];
        } else {
            //Extract db name from dsn
            $dsnParts = explode('/', $dsn);
            if (!empty($dsnParts[3])) {
                $db = $dsnParts[3];
            }
        }

        if (!$db) {
            throw new Exception\InvalidArgumentException(
                __FUNCTION__ . ' expects a "db" key to be present or it must be contained into "dsn" value'
            );
        }

        if (isset($driverOptions['options'])) {
            $mongoClient = new $mongoClass($dsn, $driverOptions['options']);
        } else {
            $mongoClient = new $mongoClass($dsn);
        }

        /** @var $mongoClient \MongoClient */
        $this->mongoDb = $mongoClient->selectDB($db);

        return true;
    }

    /**
     * @throws Exception\ConnectionException
     * @return MongoDB
     */
    public function getMongoDb()
    {
        if (!$this->mongoDb) {
            throw new Exception\ConnectionException('Not yet connected to MongoDB');
        }
        return $this->mongoDb;
    }

    /**
     * Returns the ID of the queue
     *
     * Name is the only ID of the collection, so if the collection exists the name will be returned
     *
     * @param string $name Queue name
     * @return string
     * @throws Exception\QueueNotFoundException
     */
    public function getQueueId($name)
    {
        if ($this->queueExists($name)) {
            return $name;
        }

        throw new Exception\QueueNotFoundException('Queue does not exist: ' . $name);
    }

    /**
     * Create a new queue
     *
     * @param  string $name Queue name
     * @return boolean
     */
    public function createQueue($name)
    {
        if ($this->queueExists($name)) {
            return false;
        }
        return (bool)$this->getMongoDb()->createCollection($name);
    }


    /**
     * Check if a queue exists
     *
     * @param  string $name
     * @return boolean
     * @throws Exception\ExceptionInterface
     */
    public function queueExists($name)
    {
        $collection = $this->getMongoDb()->selectCollection($name);
        $result = $collection->validate();
        if (isset($result['capped']) && $result['capped']) {
            throw new Exception\RuntimeException('Collection exists, but is capped');
        }
        return (isset($result['valid']) && $result['valid']);
    }

    /**
     * Delete a queue and all of its messages
     *
     * Return false if the queue is not found, true if the queue exists.
     *
     * @param  string $name Queue name
     * @return boolean
     */
    public function deleteQueue($name)
    {
        if (!$this->queueExists($name)) {
            return false;
        }

        $result = $this->getMongoDb()->selectCollection($name)->drop();
        return (isset($result['ok']) && $result['ok']);
    }

    /**
     * Send a message to the queue
     *
     * @param  QueueInterface $queue
     * @param  MessageInterface $message Message to send to the active queue
     * @param  SendParametersInterface $params
     * @return MessageInterface
     * @throws Exception\QueueNotFoundException
     * @throws Exception\RuntimeException
     */
    public function sendMessage(
        QueueInterface $queue,
        MessageInterface $message,
        SendParametersInterface $params = null
    ) {
        $this->cleanMessageInfo($queue, $message);

        $collection = $this->getMongoDb()->selectCollection($queue->getName());

        $id = new MongoId;
        $msg = [
            '_id' => $id,
            self::KEY_CLASS => get_class($message),
            self::KEY_CONTENT => (string)$message->getContent(),
            self::KEY_METADATA => $message->getMetadata(),
            self::KEY_HANDLE => false,
        ];

        try {
            $collection->insert($msg);
        } catch (\Exception $e) {
            throw new Exception\RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        $this->embedMessageInfo($queue, $message, $id, $params ? $params->toArray() : []);

        return $message;
    }

    /**
     * @param MongoCollection $collection
     * @param ReceiveParametersInterface $params
     * @param array $criteria
     * @param array $fields
     * @return \MongoCursor
     */
    protected function setupCursor(
        MongoCollection $collection,
        ReceiveParametersInterface $params = null,
        $criteria = [self::KEY_HANDLE => false],
        array $fields = ['_id', self::KEY_HANDLE]
    ) {
        if ($params) {
            if ($params->getClassFilter()) {
                $criteria[self::KEY_CLASS] = $params->getClassFilter();
            }
        }

        return $collection->find($criteria, $fields);
    }

    /**
     * @param QueueInterface $queue
     * @param MongoCollection $collection
     * @param mixed $id
     * @return array|null
     */
    protected function receiveMessageAtomic(QueueInterface $queue, MongoCollection $collection, $id)
    {
        $msg = $collection->findAndModify(
            ['_id' => $id],
            ['$set' => [self::KEY_HANDLE => true]],
            null,
            [
                'sort' => ['$natural' => 1],
                'new' => false, //message returned does not include the modifications made on the update
            ]
        );

        //if message has been handled already then ignore it
        if (empty($msg) || $msg[self::KEY_HANDLE]) { //already handled
            return null;
        }

        $msg[self::KEY_METADATA] = (array)$msg[self::KEY_METADATA];
        $msg[self::KEY_METADATA][$queue->getOptions()->getMessageMetadatumKey()] = $this->buildMessageInfo(
            true,
            $msg['_id'],
            $queue
        );

        return [
            'class' => $msg[self::KEY_CLASS],
            'content' => $msg[self::KEY_CONTENT],
            'metadata' => $msg[self::KEY_METADATA]
        ];
    }

    /**
     * Get messages from the queue
     *
     * @param  QueueInterface $queue
     * @param  integer|null $maxMessages Maximum number of messages to return
     * @param  ReceiveParametersInterface $params
     * @return MessageIterator
     */
    public function receiveMessages(
        QueueInterface $queue,
        $maxMessages = null,
        ReceiveParametersInterface $params = null
    ) {
        if ($maxMessages === null) {
            $maxMessages = 1;
        }

        $msgs = [];

        if ($maxMessages > 0) {
            $collection = $this->getMongoDb()->selectCollection($queue->getName());

            $cursor = $this->setupCursor($collection, $params);
            $cursor->limit((int)$maxMessages);

            foreach ($cursor as $msg) {
                $msg = $this->receiveMessageAtomic($queue, $collection, $msg['_id']);
                if ($msg) {
                    $msgs[] = $msg;
                }
            }
        }

        $classname = $queue->getOptions()->getMessageSetClass();
        return new $classname($msgs, $queue);
    }

    /**
     * Returns the approximate number of messages in the queue
     *
     * @param  QueueInterface $queue
     * @return integer
     */
    public function countMessages(QueueInterface $queue)
    {
        $collection = $this->getMongoDb()->selectCollection($queue->getName());
        return $collection->count([self::KEY_HANDLE => false]);
    }
}
