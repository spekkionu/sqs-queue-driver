# AWS SQS driver for pmg/queue
==============================

[![Latest Stable Version](https://poser.pugx.org/spekkionu/sqs-queue-driver/v/stable.png)](https://packagist.org/packages/spekkionu/sqs-queue-driver)
[![Total Downloads](https://poser.pugx.org/spekkionu/sqs-queue-driver/downloads.png)](https://packagist.org/packages/spekkionu/sqs-queue-driver)
[![Build Status](https://travis-ci.org/spekkionu/sqs-queue-driver.svg?branch=master)](https://travis-ci.org/spekkionu/sqs-queue-driver)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/da522505-6821-4941-9709-381913d0be6e/mini.png)](https://insight.sensiolabs.com/projects/da522505-6821-4941-9709-381913d0be6e)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/spekkionu/sqs-queue-driver/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/spekkionu/sqs-queue-driver/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/spekkionu/sqs-queue-driver/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/spekkionu/sqs-queue-driver/?branch=master)

```php
<?php
use Aws\Sqs\SqsClient;
use PMG\Queue\Serializer\NativeSerializer;
Spekkionu\PMG\Queue\Sqs\Driver\SqsDriver;

$sqsClient = new SqsClient([
    'credentials' => [
        'key' => 'KEY',
        'secret' => 'SECRECT'
    ],
    'region' => 'us-east-1',
    'retries' => 3,
    'version' => '2012-11-05'
]);

$serializer = new NativeSerializer();
$driver = new SqsDriver($sqsClient, $serializer);

```