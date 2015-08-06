<?php
/**
 * Stakhanovist
 *
 * @link        https://github.com/stakhanovist/queue
 * @copyright   Copyright (c) 2015, Stakhanovist
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */
namespace StakhanovistQueueAdapterMongoDbTest\TestAsset;

use MongoCollection;
use Stakhanovist\Queue\Adapter\AbstractAdapter;
use Stakhanovist\Queue\Adapter\MongoDb\AbstractMongo;
use Stakhanovist\Queue\Parameter\ReceiveParametersInterface;
use Stakhanovist\Queue\QueueInterface;

/**
 * Class ConcreteMongo
 */
class ConcreteMongo extends AbstractMongo
{
    /**
     * @param array $options
     */
    public function __construct($options = [])
    {
        // Bypass Mongo extension check
        AbstractAdapter::__construct($options);
    }

    /**
     * @param MongoCollection $collection
     * @param ReceiveParametersInterface $params
     * @param array $criteria
     * @param array $fields
     * @return \MongoCursor
     */
    public function setupCursor(
        MongoCollection $collection,
        ReceiveParametersInterface $params = null,
        $criteria = [self::KEY_HANDLE => false],
        array $fields = ['_id', self::KEY_HANDLE]
    ) {
        return parent::setupCursor($collection, $params, $criteria, $fields);
    }

    /**
     * @param QueueInterface $queue
     * @param MongoCollection $collection
     * @param mixed $id
     * @return array|null
     */
    public function receiveMessageAtomic(QueueInterface $queue, MongoCollection $collection, $id)
    {
        return parent::receiveMessageAtomic($queue, $collection, $id);
    }
}
