

1)Create new aws lambda with custom runtime 
2)Create a layer with included zip (Lambda/Layer/runtime.zip)
3)Using the layers ARN add the layer to the lambda
4)Create/Upload the included labmda function (Lambda/Function.php)
5)Set the lambda handler to "Function.handler" (this will look for our file Function.php in the task root)

Usage:
run "php TowerEquity.php 999.9999999999999 -0.9999999999999" to calculate the sum of these two numbers
run "php TowerEquity_ProcQueue.php" to process the AWS message queue 
