<?php

namespace Oro\Bundle\MessageQueueBundle\Tests\Functional\Consumption\Extension;

use Oro\Bundle\SecurityBundle\Tests\Unit\Form\Extension\TestLogger;
use Oro\Bundle\TestFrameworkBundle\Async\ChangeConfigProcessor;
use Oro\Bundle\TestFrameworkBundle\Async\Topics;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Consumption\ChainExtension;
use Oro\Component\MessageQueue\Consumption\Extension\LimitConsumedMessagesExtension;
use Oro\Component\MessageQueue\Consumption\Extension\LoggerExtension;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Consumption\QueueConsumer;
use Oro\Component\MessageQueue\Transport\Dbal\DbalConnection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InterruptConsumptionExtensionTest extends WebTestCase
{
    /**
     * @var MessageProducerInterface
     */
    protected $producer;

    /**
     * @var MessageProcessorInterface
     */
    protected $messageProcessor;

    /**
     * @var TestLogger
     */
    protected $logger;

    /**
     * @var QueueConsumer
     */
    protected $consumer;

    protected function setUp()
    {
        $this->initClient();
        $container = self::getContainer();
        $this->producer = $container->get('oro_test.client.message_producer');
        $this->messageProcessor = $container->get('oro_message_queue.client.delegate_message_processor');
        $this->logger = new TestLogger();
        $this->consumer = $container->get('oro_test.consumption.queue_consumer');
        $this->clearMessages();
    }

    protected function tearDown()
    {
        $this->clearMessages();
    }

    public function testMessageConsumptionIsNotInterruptedByMessageLimit()
    {
        $this->producer->send(Topics::CHANGE_CONFIG, ChangeConfigProcessor::COMMAND_NOOP);
        $this->producer->send(Topics::CHANGE_CONFIG, ChangeConfigProcessor::COMMAND_NOOP);

        $this->consumer->bind('oro.default', $this->messageProcessor);
        $this->consumer->consume(new ChainExtension([
            new LimitConsumedMessagesExtension(4),
            new LoggerExtension($this->logger)
        ]));

        $this->assertInterruptionMessage('Consuming interrupted, reason: The message limit reached.');
    }

    public function testMessageConsumptionIsInterruptedByConfigCacheChanged()
    {
        $this->producer->send(Topics::CHANGE_CONFIG, ChangeConfigProcessor::COMMAND_CHANGE_CACHE);
        $this->producer->send(Topics::CHANGE_CONFIG, ChangeConfigProcessor::COMMAND_CHANGE_CACHE);

        $this->consumer->bind('oro.default', $this->messageProcessor);
        $this->consumer->consume(new ChainExtension([
            new LimitConsumedMessagesExtension(4),
            new LoggerExtension($this->logger)
        ]));

        $this->assertInterruptionMessage('Consuming interrupted, reason: The cache has changed.');
    }

    /**
     * @param string $expectedMessage
     */
    private function assertInterruptionMessage(string $expectedMessage)
    {
        $this->assertTrue($this->logger->hasRecord($expectedMessage, 'warning'));
    }

    private function clearMessages()
    {
        $connection = self::getContainer()->get(
            'oro_message_queue.transport.dbal.connection',
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        );
        if ($connection instanceof DbalConnection) {
            $connection->getDBALConnection()->executeQuery('DELETE FROM ' . $connection->getTableName());
        }
    }
}
