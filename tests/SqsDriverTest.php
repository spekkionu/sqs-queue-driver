<?php
namespace Spekkionu\PMG\Queue\Test;

use Mockery;
use PHPUnit\Framework\TestCase;
use Spekkionu\PMG\Queue\Sqs\Driver\SqsDriver;

class SqsDriverTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    public function testConstructor()
    {
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $queueUrls = [];
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);

        $this->assertInstanceOf('Spekkionu\PMG\Queue\Sqs\Driver\SqsDriver', $driver);
    }

    public function testEnqueue()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $messageId = 'messageid';
        $messageBody = 'message body';
        $serializedMessageBody = json_encode($messageBody);
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldReceive('sendMessage')->with(['QueueUrl' => $queueUrls['q'], 'MessageBody' => $serializedMessageBody])->once()->andReturn(['MessageId' => $messageId]);
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $serializer->shouldReceive('serialize')->once()->andReturn($serializedMessageBody);
        
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);

        $message = Mockery::mock('PMG\Queue\Message');

        $env = $driver->enqueue('q', $message);
        $this->assertInstanceOf('Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope', $env);
        $this->assertEquals($messageId, $env->getMessageId());
    }

    public function testDequeue()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $messageId = 'messageid';
        $messageBody = 'message body';
        $receiptHandle = 'ReceiptHandle';
        $serializedMessageBody = json_encode($messageBody);
        $message = new \PMG\Queue\SimpleMessage('SimpleMessage', $messageBody);
        $wrapped = new \PMG\Queue\DefaultEnvelope($message, 1);
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldReceive('receiveMessage')->with([
                'QueueUrl' => $queueUrls['q'], 
                'MaxNumberOfMessages' => 1, 
                'AttributeNames' => ['ApproximateReceiveCount']
            ])->once()->andReturn([
                'Messages' => [
                    [
                        'MessageId' => $messageId,
                        'Body' => $serializedMessageBody,
                        'Attributes' => ['ApproximateReceiveCount' => 1],
                        'ReceiptHandle' => $receiptHandle
                    ]
                ]
            ]);
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $serializer->shouldReceive('unserialize')->with($serializedMessageBody)->once()->andReturn($wrapped);
    
        
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $env = $driver->dequeue('q');
        $this->assertInstanceOf('Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope', $env);
        $this->assertEquals($messageId, $env->getMessageId());
        $this->assertEquals($receiptHandle, $env->getReceiptHandle());
    }

    public function testDequeueWithNoResult()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $messageId = 'messageid';
        $messageBody = 'message body';
        $receiptHandle = 'ReceiptHandle';
        $serializedMessageBody = json_encode($messageBody);
        $message = new \PMG\Queue\SimpleMessage('SimpleMessage', $messageBody);
        $wrapped = new \PMG\Queue\DefaultEnvelope($message, 1);
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldReceive('receiveMessage')->with([
                'QueueUrl' => $queueUrls['q'],
                'MaxNumberOfMessages' => 1,
                'AttributeNames' => ['ApproximateReceiveCount']
            ])->once()->andReturn(null);
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $serializer->shouldNotReceive('unserialize');
    
        
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $this->assertNull($driver->dequeue('q'));
    }

    public function testAck()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $receiptHandle = 'ReceiptHandle';
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldReceive('deleteMessage')->with(['QueueUrl' => $queueUrls['q'], 'ReceiptHandle' => $receiptHandle])->once();
        $env = Mockery::mock('Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope');
        $env->shouldReceive('getReceiptHandle')->once()->andReturn($receiptHandle);
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $driver->ack('q', $env);
    }

    public function testAckWithInvalidEnvelope()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldNotReceive('deleteMessage');
        $env = Mockery::mock('PMG\Queue\Envelope');
        $env->shouldNotReceive('getReceiptHandle');

        $this->expectException('PMG\Queue\Exception\InvalidEnvelope');
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $driver->ack('q', $env);
    }

    public function testRetry()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $env = Mockery::mock('Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope');
        $env->shouldReceive('retry')->once()->andReturn($env);

        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $this->assertEquals($env, $driver->retry('q', $env));
    }

    public function testFail()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $receiptHandle = 'ReceiptHandle';
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldReceive('deleteMessage')->with(['QueueUrl' => $queueUrls['q'], 'ReceiptHandle' => $receiptHandle])->once();
        $env = Mockery::mock('Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope');
        $env->shouldReceive('getReceiptHandle')->once()->andReturn($receiptHandle);

        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $driver->fail('q', $env);
    }

    public function testRelease()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $receiptHandle = 'ReceiptHandle';
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldReceive('deleteMessage')->with(['QueueUrl' => $queueUrls['q'], 'ReceiptHandle' => $receiptHandle])->once();
        $env = Mockery::mock('Spekkionu\PMG\Queue\Sqs\Envelope\SqsEnvelope');
        $env->shouldReceive('getReceiptHandle')->once()->andReturn($receiptHandle);

        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $driver->release('q', $env);
    }

    public function testGetQueueUrl()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $this->assertEquals($queueUrls['q'], $driver->getQueueUrl('q'));
    }

    public function testGetQueueUrlFromAws()
    {
        $queueUrls = [];
        $url = 'queueurl';
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldReceive('getQueueUrl')->with(['QueueName' => 'b'])->once()->andReturn(['QueueUrl' => $url]);
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');
        
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $this->assertEquals($url, $driver->getQueueUrl('b'));
    }

    public function testGetQueueUrlNotFound()
    {
        $queueUrls = [
            'q' => 'http://queueurl.com'
        ];
        $sqsclient = Mockery::mock('Aws\Sqs\SqsClient');
        $sqsclient->shouldReceive('getQueueUrl')->with(['QueueName' => 'b'])->once()->andReturn(null);
        $serializer = Mockery::mock('PMG\Queue\Serializer\Serializer');

        $this->expectException('InvalidArgumentException', "Queue url for queue b not found");
        
        $driver = new SqsDriver($sqsclient, $serializer, $queueUrls);
        $driver->getQueueUrl('b');
    }
}
