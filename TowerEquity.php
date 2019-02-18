#!/usr/bin/php
<?php
require __DIR__ . '/vendor/autoload.php';
use Aws\Sqs\SqsClient;
use Aws\Sqs\Exception\SqsException;
use Aws\Lambda\LambdaClient;

$client = SqsClient::factory([
  'profile' => 'default',
  'region'  => 'us-east-2'
  ]);

$queueName='TowerEquity-MessageQueue';



// php TowerEquity.php 999.9999999999999 -0.9999999999999

// $queueUrl = 'https://sqs.us-east-2.amazonaws.com/258675133899/TowerEquity-MessageQueue';
$queueUrl = '';

try {
  $result = $client->getQueueUrl([
    'QueueName' => $queueName
    ]);
  $queueUrl = $result->get('QueueUrl');
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


$lambdaClient = LambdaClient::factory([
  'version' => 'latest',
  'region'  => 'us-east-2',
  ]);

$X=0;
$Y=0;
if(isset($argv)){
//only allow 0-9, periods, and minus signs
  if(isset($argv[1])){$X=preg_replace('/[^0-9.\-]+/', '', $argv[1]);}
  if(isset($argv[2])){$Y=preg_replace('/[^0-9.\-]+/', '', $argv[2]);}
}


if (!preg_match('/([-]?\d+)(\.\d+)?$/', $X,$sanity1) || !preg_match('/([-]?\d+)(\.\d+)?$/', $Y,$sanity2)) {
  die('Invalid Input, unable to detect floating point values or whole numbers.');
}

$X=$sanity1[1].((isset($sanity1[2]))? $sanity1[2]: '');
$Y=$sanity2[1].((isset($sanity2[2]))? $sanity2[2]: '');


// $X="180000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000.99";
// $Y="180000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000";

//~ printf("X: %s\n",$X);
//~ printf("Y: %s\n",$Y);
//invoke our php lambda function
$result = $lambdaClient->invoke([
  'FunctionName' => 'PHP',
  'Payload' => json_encode([$X,$Y])
  ]);

//display lambda sum result
if( isset($result) && !empty($result)){
//~ $sum=bcadd($X,$Y,14);//using bc math
//~ printf("Sum bcadd: %s\n",$sum);
//~ $sum=AddF($X,$Y);//using local AddF
$sum=$result->get('z');//using lambda AddF


//display the sum
printf("Sum: %s\n",$sum);

//determine if the sum is greater than 8 bytes. 
if(Over8Bytes($sum)){
//If the sum is greater than 8 bytes, the PHP program should save the sum
  echo "Over 8 bytes: save the sum".PHP_EOL;
  SaveSum($sum);

}else{
//queue the 8 byte integer in an AWS message queue
  $tmpInt=intval($sum);
  printf("8 byte integer: %s\n",$tmpInt);
  $client->sendMessage([
    'QueueUrl'    => $queueUrl,
    'MessageBody' => $tmpInt,
    ]);
}

}





function SaveSum($sum){
//do save ... to log file maybe? with error sum over 8 bytes

  $largeSumsFile = fopen('LargeSums.txt', 'a');
  $LogEntry=sprintf ("Over8Bytes: %s\n",$sum);
  fwrite($largeSumsFile, $LogEntry); 
  fclose($largeSumsFile);
}


function Over8Bytes($num){
  return ((is_infinite(floatval($num)))? true:false) ;
}


function AddF($Num1,$Num2,$Scale=null) { 

  if($Num1=='0'){ return $Num2; }
  if($Num2=='0'){ return $Num1; }
  $Num1Sign='';
  $Num2Sign='';

  $Num1neg=false;
  $Num2neg=false;

  if($Num1{0} == '-'){
    $Num1neg=true;
    $Num1Sign='-';
    $Num1=ltrim($Num1,'-');
  }
  if($Num2{0} == '-'){
    $Num2neg=true;
    $Num2Sign='-';
    $Num2=ltrim($Num2,'-');
  }

//extract the whole numbers and decimals or return zero
  if(!preg_match("/(\d+)(\.\d+)?$/",$Num1,$Tmp1)|| 
    !preg_match("/(\d+)(\.\d+)?$/",$Num2,$Tmp2)) return('0'); 


// this is where the result is stored 
    $Output=array(); 

// remove ending zeroes from decimals and remove point 
  $Dec1=isset($Tmp1[2])?rtrim(substr($Tmp1[2],1),'0'):''; 
  $Dec2=isset($Tmp2[2])?rtrim(substr($Tmp2[2],1),'0'):''; 

// calculate the longest length of decimals 
  $DLen=DoMax(strlen($Dec1),strlen($Dec2)); 

// if $Scale is null, automatically set it to the amount of decimal places for accuracy 
  if($Scale==null) $Scale=$DLen; 

// remove leading zeroes and reverse the whole numbers, then append padded decimals on the end 
  $Num1=strrev(ltrim($Tmp1[1],'0').str_pad($Dec1,$DLen,'0')); 
  $Num2=strrev(ltrim($Tmp2[1],'0').str_pad($Dec2,$DLen,'0')); 



// calculate the longest length we need to process 
  $lennum1=strlen($Num1);
  $lennum2=strlen($Num2); 
  $MLen=DoMax($lennum1,$lennum2); 

// pad the two numbers so they are of equal length (both equal to $MLen) 
  $Num1=str_pad($Num1,$MLen,'0'); 
  $Num2=str_pad($Num2,$MLen,'0'); 


  $positiveResult=true;
  if((!$Num1neg && $Num2neg)){    
    if($lennum1<$lennum2){
      $positiveResult=false;
    }        
  }else if(($Num1neg && !$Num2neg)){

    if($lennum1>=$lennum2){
      $positiveResult=false;
    }
  }else if(($Num1neg && $Num2neg)){

    $positiveResult=false;
  }



// process each digit, carry or borrow as needed
  for($i=0;$i<$MLen;$i++) { 
//cast each digit in string as integer

    if((!$Num1neg && $Num2neg)){

      if($lennum1>=$lennum2){
        $Sum=DoSubtract((int)($Num1{$i}),(int)($Num2{$i}));   
      }else{
        $Sum=DoSubtract((int)($Num2{$i}),(int)($Num1{$i}));      
      }


    }else if(($Num1neg && !$Num2neg)){


      if($lennum1>=$lennum2){
        $Sum=DoSubtract((int)($Num1{$i}),(int)($Num2{$i}));    
      }else{
        $Sum=DoSubtract((int)($Num2{$i}),(int)($Num1{$i}));   
      }



    }else if( ($Num1neg && $Num2neg) ){
      $Sum=DoAdd((int)($Num1{$i}),(int)($Num2{$i}));  
    }else{
      $Sum=DoAdd((int)($Num1Sign.$Num1{$i}),(int)($Num2Sign.$Num2{$i}));  
    }



//if subtracting more than avail need to borrow
    if( ((!$Num1neg && $Num2neg) || ($Num1neg && !$Num2neg) ) && ($i>0 && $Output[DoSubtract($i, 1)]<0) ){

      $Sum=DoSubtract($Sum, 1);
      $Output[DoSubtract($i, 1)]=DoAdd($Output[DoSubtract($i, 1)], 10);
    }
//recursively roll out numbers
    for($ix=$i-1;$ix>=0;$ix--) {
      $slotval=$Output[$ix];
      if($slotval<0){

        $Output[DoAdd($ix, 1)]=DoSubtract($slotval, 1);
        $Output[$ix]=DoAdd($Output[$ix], 10); 
      }
    }


//add carry 
    if(isset($Output[$i])){
      $Sum=DoAdd($Sum,$Output[$i]);
    }

//set borrow
    if($Sum<-9){     
      $Sum=0;
      $Output[DoAdd($i,1)]=-1;
    }

//set output digit
    $Output[$i]=DoRemainder($Sum,10); 

//set carry
    if($Sum>9){     
      $Output[DoAdd($i,1)]=1;
    }
  } 


//make sure digits stay in  order
  ksort($Output);

// convert the array to string and reverse it 
  $Output=strrev(implode($Output)); 
  if(!$positiveResult){  
    $Output='-'.str_replace('-','',$Output);
  }

// substring the decimal digits from the result, pad if necessary (if $Scale > amount of actual decimals) 
// next, since actual zero values can cause a problem with the substring values, if so, just simply give '0' 
// next, append the decimal value, if $Scale is defined, and return result 
  $Decimal=str_pad( (($DLen>0)? substr($Output,-$DLen,$Scale):'') ,$Scale,'0'); 
  $Output=((DoSubtract($MLen,$DLen)<1)?'0':(($DLen>0)? substr($Output,0,-$DLen):$Output)); 
  $Output.=(($Scale>0)?".{$Decimal}":''); 
  return($Output); 
} 


function DoMax($x, $y) { 
  return $x ^ (($x ^ $y) & - ($x < $y));  
} 





function DoMultiply($x, $y) 
{ 
/* 0 multiplied with  
anything gives 0 */
if($y == 0) 
  return 0; 

/* Add x one by one */
if($y > 0 ) 
  return (DoAdd($x , DoMultiply($x, DoSubtract($y,1)))); 

/* the case where  
y is negative */
if($y < 0 ) 
  return DoMultiply($x, -$y); 
} 


function DoRemainder($num, $divisor) 
{ 
  $tmpsigncheck=((string)$num)[0];
  $outputNegative=(($tmpsigncheck=='-')? true:false );
  if($outputNegative){
    $num=ltrim($num,'-');
  }
// Handle divisor equals to 0 case 
  if ($divisor == 0) 
  { 
    echo "Error: divisor can't be zero \n"; 
    return -1; 
  } 

// Handle negative values 
  if ($divisor < 0) $divisor = DoSubtract($divisor,$divisor); 
  if ($num < 0)     $num = DoSubtract($num,$num); 

// Find the largest product of 'divisor' 
// that is smaller than or equal to 'num' 
  $i = 1; 
  $product = 0; 
  while ($product <= $num) 
  { 
    $product = DoMultiply($divisor,$i); 
    $i++; 
  } 

// return remainder 
  $Remainder=DoSubtract($num ,DoSubtract($product,$divisor));

  if($outputNegative && (string)$Remainder != '0'){  
    $Remainder='-'.$Remainder;
  }
  return $Remainder; 
} 



function DoSubtract($x, $y) {      
  $x=(int)$x;
  $y=(int)$y;
  while ($y != 0) 
  { 

// borrow contains common set  
// bits of y and unset 
// bits of x 
    $borrow = (~$x) & $y; 

// DoSubtraction of bits of x  
// and y where at least 
// one of the bits is not set 

    $x = $x ^ $y; 

// Borrow is shifted by one so 
// that DoSubtracting it from 
// x gives the required sum 

    $y = $borrow << 1; 
  } 
  return $x; 
} 

function DoAdd($x , $y){
  $x=(int)$x;
  $y=(int)$y;

  while ($y != 0) 
  { 

// carry now contains common  
//set bits of x and y 
    $carry = $x & $y;  

// Sum of bits of x and y where at  
//least one of the bits is not set 
    $x = $x ^ $y;  

// Carry is shifted by one  
// so that adding it to x  
// gives the required sum 
    $y = $carry << 1; 
  } 
  return $x; 
}



echo "complete\n";

?>
