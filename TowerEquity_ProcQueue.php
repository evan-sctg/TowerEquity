#!/usr/bin/php
<?php
require __DIR__ . '/vendor/autoload.php';
use Aws\Sqs\SqsClient;

$client = SqsClient::factory([
  'profile' => 'default',
  'region'  => 'us-east-2'
  ]);

$queueName='TowerEquity-MessageQueue';



// $queueUrl = 'https://sqs.us-east-2.amazonaws.com/258675133899/TowerEquity-MessageQueue';
$queueUrl = '';

try {
  $result = $client->getQueueUrl([
    'QueueName' => $queueName
    ]);
  $queueUrl = $result->get('QueueUrl');
  //~ echo $queueUrl;
} catch (SqsException $e) {
// output error message if fails
  if($e->getAwsErrorCode()=='AWS.SimpleQueueService.NonExistentQueue'){
    try {
      $result = $client->createQueue(array('QueueName' => $queueName));
      $queueUrl = $result->get('QueueUrl');
    } catch (SqsException $e) {
// output error message if fails
      error_log(sprintf ("Create queue error: %s\n",$e->getMessage()));
    }
  }else{    
    error_log(sprintf ("Fetch queue url error: %s\n",$e->getMessage()));
  }
}


//Make a PHP program which will process the AWS message queue item 


$resultsFile = fopen('Results.txt', 'a');
$emptyQueueCount=0;
while( $emptyQueueCount<3){// If the number of messages in the queue is extremely small, you might not receive any messages in a particular ReceiveMessage response. If this happens, repeat the request.
  $result = $client->receiveMessage(array(
    'QueueUrl' => $queueUrl,
    'MaxNumberOfMessages' => 10,
    'MessageAttributeNames' => ['All'],
    ));


  $QueueMessages=$result->get('Messages');
  $mesageCnt=count($QueueMessages);
  if($mesageCnt<1){
    $emptyQueueCount++;
  }else{

    foreach ( $QueueMessages as $QueueMessage) {

      $sum=$QueueMessage['Body'];
//compute the modulus with an integer of 10. 
      $modulus=((int)$sum%10);
//Upon completion of items in the queue, the results should be written to a log file located within the directory of the program outlined in step one. 
      $ResultsEntry=sprintf ("modulus: %d  |  8 byte integer: %s\n",$modulus,$sum);
      echo $ResultsEntry;
      fwrite($resultsFile, $ResultsEntry); 

      $client->deleteMessage([
        'QueueUrl' => $queueUrl,
        'ReceiptHandle' => $QueueMessage['ReceiptHandle'], 
        ]);

    }
  }
}
fclose($resultsFile);


echo "complete\n";

?>
