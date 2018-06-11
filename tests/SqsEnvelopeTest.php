<?php
namespace Spekkionu\PMG\Queue\Test;

use PHPUnit\Framework\TestCase;
use Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope;
use \Mockery as m;

class SqsEnvelopeTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    public function tearDown()
    {
        m::close();
    }

    public function testConstructor()
    {
        $message_id = 'messageid';
        $env = m::mock('PMG\Queue\Envelope');
        $receiptHandle = null;
        $envelope = new SqsEnvelope($message_id, $env, $receiptHandle);

        $this->assertInstanceOf('Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope', $envelope);
    }

    public function testUnwrap()
    {
        $message_id = 'messageid';
        $env = m::mock('PMG\Queue\Envelope');
        $env->shouldReceive('unwrap')->once()->andReturn('unwrapped');
        $receiptHandle = null;
        $envelope = new SqsEnvelope($message_id, $env, $receiptHandle);

        $this->assertEquals('unwrapped', $envelope->unwrap());
    }

    public function testAttempts()
    {
        $message_id = 'messageid';
        $env = m::mock('PMG\Queue\Envelope');
        $env->shouldReceive('attempts')->once()->andReturn('attempted');
        $receiptHandle = null;
        $envelope = new SqsEnvelope($message_id, $env, $receiptHandle);

        $this->assertEquals('attempted', $envelope->attempts());
    }

    public function testRetry()
    {
        $message_id = 'messageid';
        $env = m::mock('PMG\Queue\Envelope');
        $env->shouldReceive('retry')->once()->andReturn('retried');
        $receiptHandle = null;
        $envelope = new SqsEnvelope($message_id, $env, $receiptHandle);

        $this->assertEquals('retried', $envelope->retry());
    }

    public function testGetMessageId()
    {
        $message_id = 'messageid';
        $env = m::mock('PMG\Queue\Envelope');
        $receiptHandle = null;
        $envelope = new SqsEnvelope($message_id, $env, $receiptHandle);

        $this->assertEquals($message_id, $envelope->getMessageId());
    }

    public function testGetReceiptHandle()
    {
        $message_id = 'messageid';
        $env = m::mock('PMG\Queue\Envelope');
        $receiptHandle = 'receiptHandle';
        $envelope = new SqsEnvelope($message_id, $env, $receiptHandle);

        $this->assertEquals($receiptHandle, $envelope->getReceiptHandle());
    }
}
