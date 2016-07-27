<?php

use MinhD\ANDSLogUtil\DatabaseAdapter;
use MinhD\ANDSLogUtil\ProcessCommand;
use Symfony\Component\Console\Application;

require 'vendor/autoload.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$databaseAdapter = new DatabaseAdapter($config = [
    'DB_HOST' => getenv('DB_HOST'),
    'DB_USER' => getenv('DB_USER'),
    'DB_PASS' => getenv('DB_PASS'),
    'DB_DATABASE' => getenv('DB_DATABASE'),
]);

$processCommand = new ProcessCommand($databaseAdapter);

date_default_timezone_set('UTC');
$portal_view = "[date:2016-04-07 09:22:07] [event:portal_view][roid:316815][roclass:party][dsid:36][group:Charles Darwin University][ip:192.168.33.1][user_agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36][username:Minh Duc Nguyen][userid:u4297901]";
$portal_search = "[date:2016-04-07 09:17:16] [event:portal_search][ip:192.168.33.1][user_agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36][anzsrc-for:2003][class:party][rows:15][sort:list_title_sort asc][q:][result_numFound:8][result_roid:11078,,11467,,316815,,316828,,11466,,2696,,9048,,316818][result_group:The University of Sydney,,Macquarie University,,Charles Darwin University,,Bond University,,Griffith University][result_dsid:69,,86,,36,,33,,61][username:Minh Duc Nguyen][userid:u4297901]";
$portal_search_2 = "[date:2016-04-20 10:03:50] [event:portal_search][ip:192.168.33.1][user_agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36][related_collection_id:708276][class:party][nottype:group][rows:15][sort:list_title_sort asc][q:][result_numFound:50][result_roid:708027,,708028,,708029,,708030,,708031,,708032,,708033,,708034,,708035,,708036,,708037,,708038,,708039,,708040,,708041][result_group:AUTestingRecords2][result_dsid:236]";
$portal_modify_user_data ='[date:2015-12-30 22:00:47] [event:portal_modify_user_data][action:add][raw:[{"id":"395364","slug":"fas-convict-ship-prosopography-index","group":"University_of_Melbourne","title":"FAS Convict Ship 368.39 Pestonjee Bomanjee (3) arrived 1849 at VDL Prosopography Index","type":"dataset","class":"collection","folder":"connolly","saved_time":1451473247,"last_viewed":1451473247}]][ip:58.162.228.80][user_agent:Mozilla/5.0 (Windows NT 6.3; ARM; Trident/7.0; Touch; rv:11.0) like Gecko][username:Cyril McGregor][userid:10206527053667422]';
$portal_tag_add = '[date:2015-08-17 14:46:14] [key:http://data.aad.gov.au/aadc/4c217549-cef3-40f4-8ac4-79a24acd3129][id:354965][user:Gerry Ryder][user_from:aaf.edu.au][event:portal_tag_add][ip:144.110.19.14][user_agent:Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)][username:Gerry Ryder][userid:oTMKL7aT53ixthdH8maPs2YBaWE]';
$portal_preview = '[date:2016-01-28 11:01:02] [event:portal_preview][roid:644722][dsid:183][group:data.gov.au][class:collection][ip:101.103.137.57][user_agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9]';

$portal_view_test_2 = "[date:2014-12-28 05:22:48] [event:portal_view][roid:11618][template:default][ip:62.210.96.36][user_agent:Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)]";

// var_dump($databaseAdapter->getRecordOwners(7));
// $databaseAdapter->test();

 $content = $processCommand->readString($portal_view_test_2);
 echo ($processCommand->processLineEvent($content, 'portal'));

// $content = $processCommand->readString($portal_search);
// echo ($processCommand->processLineEvent($content, 'portal'));

// $content = $processCommand->readString($portal_search_2);
// echo ($processCommand->processLineEvent($content, 'portal'));

//$content = $processCommand->readString($portal_modify_user_data);
//echo ($processCommand->processLineEvent($content, 'portal'));

// $content = $processCommand->readString($portal_tag_add);
// echo ($processCommand->processLineEvent($content, 'portal'));

// $content = $processCommand->readString($portal_preview);
// echo ($processCommand->processLineEvent($content, 'portal'));
