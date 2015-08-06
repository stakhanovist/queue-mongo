<?php
/**
 * Stakhanovist
 *
 * @link        https://github.com/stakhanovist/queue
 * @copyright   Copyright (c) 2015, Stakhanovist
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */

namespace Stakhanovist\Queue\Adapter\MongoDb;

use Stakhanovist\Queue\Adapter\Capabilities\DeleteMessageCapableInterface;
use Stakhanovist\Queue\Adapter\Mongo\AbstractMongo;
use Stakhanovist\Queue\Exception;
use Stakhanovist\Queue\QueueInterface;
use Zend\Stdlib\MessageInterface;

/**
 * Class MongoCollection
 */
class MongoCollection extends AbstractMongo implements DeleteMessageCapableInterface
{
    /**
     * Delete a message from the queue
     *
     * Return true if the message is deleted, false if the deletion is
     * unsuccessful.
     *
     * @param  QueueInterface $queue
     * @param  MessageInterface $message
     * @return boolean
     * @throws Exception\QueueNotFoundException
     */
    public function deleteMessage(QueueInterface $queue, MessageInterface $message)
    {
        $info = $this->getMessageInfo($queue, $message);
        if (!isset($info['messageId']) || !isset($info['handle'])) {
            return false;
        }

        $collection = $this->getMongoDb()->selectCollection($queue->getName());
        $result = $collection->remove(['_id' => $info['messageId'], self::KEY_HANDLE => $info['handle']]);
        $deleted = (isset($result['ok']) && $result['ok']);

        if ($deleted) {
            $this->cleanMessageInfo($queue, $message);
        }
        return $deleted;
    }
}
