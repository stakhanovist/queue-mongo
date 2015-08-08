<?php
/**
 * Stakhanovist
 *
 * @link        https://github.com/stakhanovist/queue
 * @copyright   Copyright (c) 2015, Stakhanovist
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */
namespace StakhanovistQueueAdapterMongoDbTest;

use Stakhanovist\Queue\Adapter\MongoDb\MongoCollection;
use StakhanovistQueueTest\Adapter\AdapterTest as QueueAdapterTest;

/**
 * Class MongoCollectionTest
 *
 * All methods marked not supported are explictly checked for for throwing an exception.
 */
class MongoCollectionTest extends QueueAdapterTest
{
    /**
     * @var string
     */
    protected $dbName;

    /**
     * @var string
     */
    protected $server;

    public function setUp()
    {
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped('The mongo PHP extension is not available');
        }

        $this->dbName = 'StakhanovistQueueMongoCollectionTest';
        $this->server = "mongodb://" . getenv('MONGODB_HOST') . ":". getenv('MONGODB_PORT');
    }

    /**
     * {@inheritdoc}
     */
    public function getAdapterFullName()
    {
        return MongoCollection::class;
    }

    /**
     * @return array
     */
    public function getTestOptions()
    {
        return [
            'driverOptions' => [
                'db' => $this->dbName,
                'dsn' => $this->server . '/' . $this->dbName
            ]
        ];
    }

    public function testSendMessageShouldThrowExcepetionWhenQueueDoesntExist()
    {
        $this->markTestSkipped('Mongo does not throw execption if collection does not exists');
    }

    public function testDeleteMessageShouldThrowExcepetionWhenQueueDoesntExist()
    {
        $this->markTestSkipped('Mongo does not throw execption if collection does not exists');
    }

    public function testCountMessageShouldThrowExcepetionWhenQueueDoesntExist()
    {
        $this->markTestSkipped('Mongo does not throw execption if collection does not exists');
    }
}
