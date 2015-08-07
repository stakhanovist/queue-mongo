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
use Stakhanovist\Queue\Adapter\Null;
use Stakhanovist\Queue\Exception\ConnectionException;
use Stakhanovist\Queue\Exception\InvalidArgumentException;
use Stakhanovist\Queue\Exception\RuntimeException;
use Stakhanovist\Queue\Queue;
use StakhanovistQueueAdapterMongoDbTest\TestAsset\ConcreteMongo;

/**
 * Class AbstractMongoTest
 *
 * @group abs
 */
class AbstractMongoTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \MongoClient
     */
    protected $mongo;

    /**
     * @var ConcreteMongo
     */
    protected $abstractMongo;

    /**
     * @var string
     */
    protected $dbName;

    /**
     * @var string
     */
    protected $collection;

    /**
     * @var \MongoDB
     */
    protected $mongoDb;

    /**
     * @var string
     */
    protected $server;

    public function setUp()
    {
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped('The mongo PHP extension is not available');
        }

        $this->dbName = 'StakhanovistQueueMongoAbstractTest';
        $this->server = "mongodb://" . getenv('MONGODB_HOST') . ":". getenv('MONGODB_PORT');

        $this->mongo = new \MongoClient($this->server . '/' . $this->dbName);
        $this->mongoDb = $this->mongo->selectDb($this->dbName);
        $this->mongoDb->drop(); // cleanup

        $this->abstractMongo = new ConcreteMongo(
            [
                'mongoDb' => $this->mongoDb
            ]
        );
    }

    public function testConnect()
    {
        // Test with params
        $abstractMongo = new ConcreteMongo(
            [
                'driverOptions' => [
                    'db' => $this->dbName,
                    'options' => ["connect" => true]
                ]
            ]
        );

        $this->assertTrue($abstractMongo->connect());

        $abstractMongo = new ConcreteMongo(
            [
                'driverOptions' => [
                    'dsn' => $this->server . '/' . $this->dbName
                ]
            ]
        );

        $this->assertTrue($abstractMongo->connect());

        // Test passing MongoDB instance
        $this->assertTrue($this->abstractMongo->connect());

        // Test invalid options excepetion
        $this->setExpectedException(InvalidArgumentException::class);
        $abstractMongo = new ConcreteMongo;
        $abstractMongo->connect();
    }

    public function testGetMongoDb()
    {
        $this->abstractMongo->connect();
        $this->assertSame($this->mongoDb, $this->abstractMongo->getMongoDb());
    }

    public function testShouldThrowExceptionOnGetMongoDbBeforeConnect()
    {
        $this->setExpectedException(ConnectionException::class);
        $this->abstractMongo->getMongoDb();
    }

    public function testShouldThrowExceptionOnExistingCappedCollection()
    {
        $mongoCappedAdapter = new MongoCappedCollection();
        $mongoCappedAdapter->setOptions($this->abstractMongo->getOptions());
        $mongoCappedAdapter->connect();
        $mongoCappedAdapter->createQueue('testExistingCappedCollection');

        $this->abstractMongo->connect();
        $this->setExpectedException(RuntimeException::class);
        $this->abstractMongo->queueExists('testExistingCappedCollection');
    }

    public function testReceiveMessageAtomicWithNoMessage()
    {
        // Assume queue is empty
        $queue = new Queue('foo', new Null); // FIXME: rename into NullAdapter
        $this->abstractMongo->connect();
        $this->assertNull(
            $this->abstractMongo->receiveMessageAtomic(
                $queue,
                $this->mongoDb->selectCollection('non-existing-collection'),
                new \MongoId
            )
        );
    }
}
