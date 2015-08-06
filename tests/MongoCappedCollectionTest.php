<?php
/**
 * Stakhanovist
 *
 * @link        https://github.com/stakhanovist/queue
 * @copyright   Copyright (c) 2015, Stakhanovist
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */
namespace StakhanovistQueueAdapterMongoDbTest;

use Stakhanovist\Queue\Adapter\MongoDb\MongoCappedCollection;
use Stakhanovist\Queue\Adapter\MongoDb\MongoCollection;
use Stakhanovist\Queue\Exception\RuntimeException;
use Stakhanovist\Queue\Message\Message;
use StakhanovistQueueTest\Adapter\AdapterTest;

/**
 * Class MongoCappedCollectionTest
 *
 * All methods marked not supported are explictly checked for for throwing an exception.
 */
class MongoCappedCollectionTest extends AdapterTest
{
    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped('The mongo PHP extension is not available');
        }
    }

    /**
     * getAdapterName() is an method to help make AdapterTest work with any
     * new adapters
     *
     * You must overload this method
     *
     * @return string
     */
    public function getAdapterName()
    {
        return 'MongoCappedCollection';
    }

    /**
     * getAdapterName() is an method to help make AdapterTest work with any
     * new adapters
     *
     * You may overload this method.  The default return is
     * 'Stakhanovist_Queue_Adapter_' . $this->getAdapterName()
     *
     * @return string
     */
    public function getAdapterFullName()
    {
        return MongoCappedCollection::class;
    }

    /**
     * @return array
     */
    public function getTestOptions()
    {
        return [
            'driverOptions' => [
                'db' => 'stakhanovist_queue_test_capped'
            ]
        ];
    }

    public function testShouldThrowExceptionOnExistingNonCappedCollection()
    {
        $mongoNonCappedAdapter = new MongoCollection();
        $mongoNonCappedAdapter->setOptions($this->getTestOptions());
        $mongoNonCappedAdapter->connect();
        $mongoNonCappedAdapter->createQueue(__FUNCTION__);

        $this->setExpectedException(RuntimeException::class);
        $this->createQueue(__FUNCTION__);
    }

    public function testSendMessageShouldThrowExcepetionWhenQueueDoesntExist()
    {
        $this->markTestSkipped('Mongo does not throw exception if collection does not exists');
    }

    public function testDeleteMessageShouldThrowExcepetionWhenQueueDoesntExist()
    {
        $this->markTestSkipped('Mongo does not throw exception if collection does not exists');
    }

    public function testCountMessageShouldThrowExcepetionWhenQueueDoesntExist()
    {
        $this->markTestSkipped('Mongo does not throw exception if collection does not exists');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSendMessageWithFullCappedCollection()
    {
        $queue = $this->createQueue(__FUNCTION__);
        $adapter = $queue->getAdapter();
        $options = $adapter->getOptions();
        $options['threshold'] = 10000;
        $adapter->setOptions($options);
        $this->checkAdapterSupport($adapter, 'sendMessage');
        $adapter->sendMessage($queue, new Message());
    }

    /**
     * @expectedException \Stakhanovist\Queue\Exception\RuntimeException
     */
    public function testAwaitMessagesWithoutSecondLast()
    {
        $queue = $this->createQueue(__FUNCTION__);
        /** @var $adapter MongoCappedCollection */
        $adapter = $queue->getAdapter();
        $this->checkAdapterSupport($adapter, ['sendMessage', 'deleteQueue']);

        $receiveCount = 0;
        $messages = null;

        $queue->send('foo');

        /** @var $collection \MongoCollection */
        $collection = $adapter->getMongoDb()->selectCollection($queue->getName());
        $collection->drop();

        $adapter->awaitMessages(
            $queue,
            function () use (&$receiveCount, &$messages, $queue) {
                return false;
            }
        );
    }
}
