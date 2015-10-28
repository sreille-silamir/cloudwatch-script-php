<?php
define('APPLICATION_PATH', realpath(dirname(__FILE__)));

include APPLICATION_PATH . '/vendor/autoload.php';
require_once 'lib.php';

use Aws\CloudWatch\CloudWatchClient;


// Load config file.
$conf = json_decode(file_get_contents(APPLICATION_PATH.'/conf/config.json'));
if($conf === false) {
    echo "Conf file is not valid";
    die();
}
// Store metric by namespace in order to call "AWS Could Watch" one time per namespace
$metricsToPush = array();

// Get Instance Id
$instanceId = file_get_contents("http://169.254.169.254/latest/meta-data/instance-id");

$client = getCloudWatchClient($conf);

foreach ($conf->metrics as $metrics) {
    foreach($metrics as $metricName => $metric){
        $className = "CloudWatchScript\\Plugins\\" . $metricName . "Monitoring";

        $metricController = new $className($metric,  $metric->name);
        
        foreach ($metricController->getAlarms() as $key => $alarm) { 
            $client->putMetricAlarm(array(
                    'AlarmName' => $alarm["Name"],
                    'AlarmDescription' => $metric->description,
                    'ActionsEnabled' => true,
                    'OKActions' => array($conf->alarms->action  ),
                    'AlarmActions' => array($conf->alarms->action ),
                    'InsufficientDataActions' => array($conf->alarms->action),
                    'Dimensions' => array(
                                    array('Name' => 'InstanceId', 'Value' => $instanceId),
                                    array('Name' => 'Metrics', 'Value' => $metricName)
                    ),
                    'MetricName' => $metric->name,
                    'Namespace' => $metric->namespace,
                    'Statistic' => 'Average',
                    'Period' => 300,
                    'Unit' => $metricController->getUnit(),
                    // EvaluationPeriods is required
                    'EvaluationPeriods' => 2,
                    // Threshold is required
                    'Threshold' => $alarm["Threshold"],
                    // ComparisonOperator is required
                    'ComparisonOperator' => $alarm["ComparisonOperator"]
            ));
        }
    }
}
