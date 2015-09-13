<?php
namespace Spekkionu\PMG\Queue\Sqs\Envelope;

use PMG\Queue\Envelope;

class SqsEnvelope implements Envelope
{
    /**
     * @var string Message ID
     */
    private $message_id;

    /**
     * @var Envelope
     */
    private $wrapped;

    /**
     * @var string|null
     */
    private $receiptHandle;

    /**
     * @param string $message_id
     * @param Envelope $wrapped
     * @param string|null $receiptHandle
     */
    public function __construct($message_id, Envelope $wrapped, $receiptHandle = null)
    {
        $this->message_id = $message_id;
        $this->wrapped = $wrapped;
        $this->receiptHandle = $receiptHandle;
    }

    /**
     * {@inheritdoc}
     */
    public function unwrap()
    {
        return $this->wrapped->unwrap();
    }

    /**
     * {@inheritdoc}
     */
    public function attempts()
    {
        return $this->wrapped->attempts();
    }

    /**
     * {@inheritdoc}
     * Returns a clone of the wrapped envelope, not itself.
     */
    public function retry()
    {
        return $this->wrapped->retry();
    }

    /**
     * Returns message id
     * @return string
     */
    public function getMessageId()
    {
        return $this->message_id;
    }

    /**
     * Returns receipt handle
     * @return string|null
     */
    public function getReceiptHandle()
    {
        return $this->receiptHandle;
    }
}
