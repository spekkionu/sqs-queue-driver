<?php
namespace Spekkionu\PMG\Queue\Sqs\Driver;

use Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope;
use Aws\Sqs\Exception\SqsException;
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
    public function __construct(SqsClient $client, Serializer $serializer, array $queueUrls = array())
    {
        parent::__construct($serializer);
        $this->client = $client;
        $this->queueUrls = $queueUrls;
    }

    /**
     * @inheritDoc
     */
    public function enqueue($queueName, Message $message)
    {
        $queueUrl = $this->getQueueUrl($queueName);

        $env = new DefaultEnvelope($message);
        $data = $this->serialize($env);

        $result = $this->client->sendMessage(array(
            'QueueUrl' => $queueUrl,
            'MessageBody' => $data
        ));

        return new SqsEnvelope($result['MessageId'], $env);
    }

    /**
     * @inheritDoc
     */
    public function dequeue($queueName)
    {
        $queueUrl = $this->getQueueUrl($queueName);

        $result = $this->client->receiveMessage(array(
            'QueueUrl' => $queueUrl,
            'MaxNumberOfMessages' => 1,
            'AttributeNames' => ['ApproximateReceiveCount']
        ));

        if (!$result || !$messages = $result['Messages']) {
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
    public function ack($queueName, Envelope $envelope)
    {
        if (!$envelope instanceof SqsEnvelope) {
            throw new InvalidEnvelope(sprintf(
                '%s requires that envelopes be instances of "%s", got "%s"',
                __CLASS__,
                SqsEnvelope::class,
                get_class($envelope)
            ));
        }

        $queueUrl = $this->getQueueUrl($queueName);

        $this->client->deleteMessage([
            'QueueUrl' => $queueUrl,
            'ReceiptHandle' => $envelope->getReceiptHandle(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function retry($queueName, Envelope $envelope)
    {
        return $envelope->retry();
    }

    /**
     * @inheritDoc
     */
    public function fail($queueName, Envelope $envelope)
    {
        return $this->ack($queueName, $envelope);
    }

    /**
     * Returns queue url
     * @param  string $queueName The name of the queue
     * @return string            The queue url
     */
    public function getQueueUrl($queueName)
    {
        if (array_key_exists($queueName, $this->queueUrls)) {
            return $this->queueUrls[$queueName];
        }
        try {
            $result = $this->client->getCommand('getQueueUrl', array('QueueName' => $queueName));
        } catch (SqsException $exception) {
            if ($exception->getExceptionCode() === 'AWS.SimpleQueueService.NonExistentQueue') {
                throw new SqsException(
                    "The queue {$queueName} is neither aliased locally to an SQS URL nor could it be resolved by SQS.",
                    $exception->getCode(),
                    $exception
                );
            }
            throw $exception;
        }
        if ($result && $queueUrl = $result['QueueUrl']) {
            return $this->queueUrls[$queueName] = $queueUrl;
        }

        throw new \InvalidArgumentException("Queue url for queue {$queueName} not found");
    }
}
