<?php
require 'vendor/autoload.php';

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Exception\AwsException;

$client = new CloudWatchLogsClient([
    'region'  => 'eu-west-1',
    'version' => 'latest',
    'profile' => 'production'
]);

$logStreamFilters = [
    'logGroupName' => '/aws/lambda/RunSnowIOPHPBinary',
    'logStreamNamePrefix' => '2018/07/04'
];

$esClient = Elasticsearch\ClientBuilder::create()->build();
$qtyStreams = $qtyEvents = 0;

foreach($client->describeLogStreams($logStreamFilters) as $logGroupResult){

    foreach($logGroupResult as $logStream){

        $esClient->index([
            'index' => 'streams',
            'type'  => 'stream',
            'id'    => md5($logStream['arn']),
            'body'  => $logStream,
        ]);

        $allLogs = $client->filterLogEvents([
            'logGroupName' => '/aws/lambda/RunSnowIOPHPBinary',
            'logStreamNames' => [$logStream['logStreamName']]
        ])->toArray();

        foreach($allLogs['events'] as $event){
            $esClient->index([
                'index' => 'events',
                'type'  => 'event',
                'id'    => $event['eventId'],
                'body'  => $event,
            ]);

            $qtyEvents += 1;
        }

        $qtyStreams += 1;
    }
}

echo $qtyStreams." streams indexed".PHP_EOL;
echo $qtyEvents." events indexed".PHP_EOL;

