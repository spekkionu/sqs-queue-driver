<?php

namespace Spekkionu\PMG\Queue\Sqs\Driver;

use Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope;
use Aws\Sqs\SqsClient;
use PMG\Queue\Driver\AbstractPersistanceDriver;
use PMG\Queue\Exception;
use PMG\Queue\Serializer\Serializer;
use PMG\Queue\DefaultEnvelope;
use PMG\Queue\Envelope;
use PMG\Queue\Message;
use PMG\Queue\Exception\InvalidEnvelope;

class SqsDriver extends AbstractPersistanceDriver
{
    /**
     * @var SqsClient
     */
    private $client;

    /**
     * @var array
     */
    private $queueUrls;

    /**
     * @param SqsClient $client
     * @param Serializer $serializer
     * @param array $queueUrls
     */
    public function __construct(SqsClient $client, Serializer $serializer, array $queueUrls = [])
    {
        parent::__construct($serializer);
        $this->client    = $client;
        $this->queueUrls = $queueUrls;
    }

    /**
     * {@inheritdoc}
     */
    public static function allowedClasses()
    {
        $cls = parent::allowedClasses();
        $cls[] = SqsEnvelope::class;
        return $cls;
    }

    /**
     * @inheritDoc
     */
    public function enqueue(string $queueName, Message $message): Envelope
    {
        $queueUrl = $this->getQueueUrl($queueName);

        $env  = new DefaultEnvelope($message);
        $data = $this->serialize($env);

        $result = $this->client->sendMessage([
            'QueueUrl'    => $queueUrl,
            'MessageBody' => $data,
        ]);

        return new SqsEnvelope($result['MessageId'], $env);
    }

    /**
     * @inheritDoc
     */
    public function dequeue(string $queueName)
    {
        $queueUrl = $this->getQueueUrl($queueName);

        $result = $this->client->receiveMessage([
            'QueueUrl'            => $queueUrl,
            'MaxNumberOfMessages' => 1,
            'AttributeNames'      => ['ApproximateReceiveCount'],
        ]);

        if ( ! $result || ! $messages = $result['Messages']) {
            return null;
        }

        $message = array_shift($messages);

        $wrapped = $this->unserialize($message['Body']);

        $msg = new DefaultEnvelope($wrapped->unwrap(), $message['Attributes']['ApproximateReceiveCount']);
        $env = new SqsEnvelope($message['MessageId'], $msg, $message['ReceiptHandle']);

        return $env;
    }

    /**
     * @inheritDoc
     */
    public function ack(string $queueName, Envelope $envelope)
    {
        if ( ! $envelope instanceof SqsEnvelope) {
            throw new InvalidEnvelope(sprintf(
                '%s requires that envelopes be instances of "%s", got "%s"',
                __CLASS__,
                SqsEnvelope::class,
                get_class($envelope)
            ));
        }

        $queueUrl = $this->getQueueUrl($queueName);

        $this->client->deleteMessage([
            'QueueUrl'      => $queueUrl,
            'ReceiptHandle' => $envelope->getReceiptHandle(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function retry(string $queueName, Envelope $envelope) : Envelope
    {
        return $envelope->retry();
    }

    /**
     * @inheritDoc
     */
    public function fail(string $queueName, Envelope $envelope)
    {
        return $this->ack($queueName, $envelope);
    }

    /**
     * Returns queue url
     *
     * @param  string $queueName The name of the queue
     *
     * @return string            The queue url
     */
    public function getQueueUrl($queueName)
    {
        if (array_key_exists($queueName, $this->queueUrls)) {
            return $this->queueUrls[$queueName];
        }

        $result = $this->client->getQueueUrl(['QueueName' => $queueName]);

        if ($result && $queueUrl = $result['QueueUrl']) {
            return $this->queueUrls[$queueName] = $queueUrl;
        }

        throw new \InvalidArgumentException("Queue url for queue {$queueName} not found");
    }

    /**
     * Release a message back to a ready state. This is used by the consumer
     * when it skips the retry system. This may happen if the consumer receives
     * a signal and has to exit early.
     *
     * @param $queueName The queue from which the message came
     * @param $envelope The message to release, should be the same instance
     *        returned from `dequeue`
     *
     * @throws Exception\DriverError if something goes wrong
     * @return void
     */
    public function release(string $queueName, Envelope $envelope)
    {
        return $this->ack($queueName, $envelope);
    }
}
