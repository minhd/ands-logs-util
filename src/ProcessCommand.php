<?php

namespace MinhD\ANDSLogUtil;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use MinhD\ANDSLogUtil\DatabaseAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * MinhD\ANDSLogUtil\ProcessCommand
 * Usage: php ands-log process --from_date --to_date --from_dir --to_dir
 */
class ProcessCommand extends Command
{

    protected $input;
    protected $output;
    protected $db;

    function __construct(DatabaseAdapter $databaseAdapter)
    {
        $this->db = $databaseAdapter;
        $this->crawlerDetect = new CrawlerDetect;
        parent::__construct();
    }

    /**
     * Configure the Command
     * @return
     */
    public function configure()
    {
        $this->setName('process')
            ->setDescription('Process the Log Directory')
            ->addOption('from_dir', null, InputOption::VALUE_OPTIONAL, "Set the from directory", '/Users/mnguyen/dev/elk/logs/')
            ->addOption('to_dir', null, InputOption::VALUE_OPTIONAL, "Set the to directory", '/Users/mnguyen/dev/elk/processed_logs/')
            ->addOption('from_date', null, InputOption::VALUE_OPTIONAL, "Process from a date yyyy-mm-dd", false)
            ->addOption('to_date', null, InputOption::VALUE_OPTIONAL, "Process to a date yyyy-mm-dd", false)
            // ->addOption('test', null, InputOption::VALUE_OPTIONAL, "Development Test", false)
            ->addArgument('type', InputArgument::OPTIONAL, 'Log type to process', 'portal');
    }

    /**
     * Execute the Command
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        // capture options
        $logType = $input->getArgument('type');
        $fromDir = $input->getOption('from_dir');
        $toDir = $input->getOption('to_dir');

        /**
         * read the From Directory to get a list of files and dates
         * @todo refactor to use symfony/finder component
         */
        $fromDir = $fromDir.$logType;
        $this->verbose('Processing directory: '. $fromDir);
        $dates = $this->readDirectory($fromDir);
        $this->debug('Found '.count($dates). ' dates for processing');

        // prepare dates array with optional from and to
        $dates = $this->prepareDates($dates, $input);

        $this->verbose('Processing '.count($dates). ' dates starting with '.reset($dates). ' ends with '.end($dates));

        // Process the logs
        foreach ($dates as $date) {
            $this->ensureDirectoryCreation([$toDir, $toDir.$logType]);
            $inputFilePath = $fromDir.'/log-'.$logType.'-'.$date.'.php';
            $outputFilePath = $toDir.$logType.'/'.$date.'.log';
            $this->processLogFile($date, $logType, $inputFilePath, $outputFilePath);
        }

        $this->output('Done!');
    }

    /**
     * Process a single log date
     * @param  string $date    yyyy-mm-dd
     * @param  string $logType
     * @param  string $inputFilePath
     * @param  string $outputFilePath
     * @return void
     */
    private function processLogFile($date, $logType, $inputFilePath, $outputFilePath)
    {
        $this->debug('Processing '. $date);
        $this->debug('File path: '. $inputFilePath);
        $lines = $this->readFileToLine($inputFilePath);
        $this->debug('Lines count: '. count($lines));

        foreach ($lines as $line) {

            $content = $this->readString($line);

            // does not convert empty event or event without date
            if (!$content
                || count($content) === 0
                || !array_key_exists('date', $content)
                || !array_key_exists('event', $content)
            ) {
                continue;
            }

            // does not convert bot events
            $content['user_agent'] = array_key_exists('user_agent', $content) ? $content['user_agent'] : 'dude';
            $content['is_bot'] = $this->isBot($content['user_agent']);
            $content['event'] = isset($content['event']) ? $content['event'] : 'unknown_event';

            if ($content['is_bot']) {
                continue;
            }

            $message = $this->processLineEvent($content, $logType);

            $this->writeToFile($outputFilePath, $message);

            // $this->debug($message);
            unset($content);
            unset($parsed);
        }
        $this->verbose('Finished '.$date);
    }

    public function processLineEvent($content, $logType)
    {

        // construct the event
        $event = array_key_exists('event', $content) ? $content['event'] : 'unknown_event';
        $parsed = [
            '@timestamp' => date("c", strtotime($content['date'])),
            '@source' => 'localhost',
            '@message' => $event,
            '@tags' => [$logType, $event],
            '@type' => $logType
        ];

        // fix/add registryObject data
        // $content = $this->parseRegistryObjectFields($content);

        // parse splittable fields logic
        $content = $this->parseSplittableFields($content);

        // parse data after content is alright
        // foreach ($content as $key=>$value) {
        //     $parsed['@fields_old'][$key] = $value;
        // }

        $fields = [];
        $fields = array_merge($fields, $this->parseEvent($content));
        $fields = array_merge($fields, $this->parseUser($content));

        foreach ($fields as $key=>$value) {
            $parsed['@fields'][$key] = $value;
        }

        $message = json_encode($parsed);
        return $message;
    }

    private function parseUser($content)
    {
        if (!isset($content['ip'])) {
            return [];
        }

        $user = [
            'ip' => $content['ip'],
            'user_agent' => $content['user_agent'],
            'is_bot' => isset($content['is_bot']) ? $content['is_bot'] : $this->isBot($content['user_agent'])
        ];

        if (array_key_exists('username', $content)) {
            $user['username'] = $content['username'];
        }

        if (array_key_exists('userid', $content)) {
            $user['userid'] = $content['userid'];
        }

        return [
            'user' => $user
        ];
    }

    private function parseEvent($content)
    {

        switch ($content['event']) {
            case "portal_view":
                return [
                    'event' => 'portal_view',
                    'channel' => 'portal',
                    'level' => 200,
                    'record' => $this->parseRecordFields($content['roid'])
                ];
                break;
            case "portal_search":
                return [
                    'event' => 'portal_search',
                    'channel' => 'portal',
                    'level' => 200,
                    'filters' => $this->parseFiltersFields($content),
                    'result' => $this->parseResultFields($content)
                ];
                break;
            case "portal_modify_user_data":
                $action = array_key_exists('action', $content) ? $content['action']: null;
                $event = [
                    'event' => 'portal_modify_user_data',
                    'channel' => 'portal',
                    'level' => 200,
                    'profile_action' => [
                        'action' => $content['action']
                    ]
                ];
                if (array_key_exists('raw', $content)
                    && $decoded = json_decode($content['raw'], true)
                    ) {
                    $event['profile_action']['data'] = $decoded;
                }
                return $event;
                break;
            case "portal_tag_add":
                $tag = array_key_exists('tag', $content) ? $content['tag'] : null;
                return [
                    'record' => $this->parseRecordFields($content['id']),
                    'tag' => ['value' => $tag]
                ];
                break;
            case "portal_preview":
                $event = [
                    'event' => 'portal_preview',
                    'channel' => 'portal',
                    'level' => 200
                ];

                if (isset($content['roid'])) {
                    $event['record'] = $this->parseRecordFields($content['roid']);
                }

                if (isset($content['identifier_relation_id'])) {
                    $event['identifier_relation_id'] = $content['identifier_relation_id'];
                }

                if (isset($content['identifier_doi'])) {
                    $event['identifier_doi'] = $content['identifier_doi'];
                }

                return $event;

                break;
            case "portal_page":
                return [
                    'event' => 'portal_page',
                    'channel' => 'portal',
                    'level' => 200,
                    'page' => $content['page']
                ];
                break;
            case "accessed":
                return [
                    'event' => 'portal_accessed',
                    'channel' => 'portal',
                    'level' => 200,
                    'record' => $this->parseRecordFields($content['roid'])
                ];
                break;
            case "portal_dashboard":
                return [
                    'event' => 'portal_dashboard',
                    'channel' => 'portal',
                    'level' => 200
                ];
                break;
            case "outbound":
                return [
                    'event' => 'portal_outbound',
                    'channel' => 'portal',
                    'level' => 200,
                    'outbound_from' => $content['from'],
                    'outbound_to' => $content['to']
                ];
                break;
            default:
                $this->debug("No handler for: ". $content['event']);
                return [
                    'event' => "unknown event: ".$content['event']
                ];
                break;
        }

    }

    private function parseRecordFields($id)
    {
        $record = $this->db->getRecord($id);
        return [
            'id' => $record['registry_object_id'],
            'key' => $record['key'],
            'class' => $record['class'],
            'type' => $record['type'],
            'data_source_id' => $record['data_source_id'],
            'slug' => $record['slug'],
            'group' => isset($record['group']) ? $record['group'] : null,
            'record_owners' => $this->db->getRecordOwners($record['data_source_id'])
        ];
    }

    private function parseFiltersFields($content)
    {
        $nonSearchFields = ['result_numFound', 'result_roid', 'result_group', 'result_dsid', 'date', 'event', 'ip', 'user_agent', 'is_bot'];
        foreach ($nonSearchFields as $field) {
            unset($content[$field]);
        }

        return $content;
    }

    private function parseResultFields($content)
    {
        $owners = [];
        if (isset($content['result_dsid']) && is_array($content['result_dsid'])) {
            foreach ($content['result_dsid'] as $dataSourceID) {
                $owners = array_merge($owners, $this->db->getRecordOwners($dataSourceID));
            }
            $owners = array_values(array_unique($owners));
        }
        return [
            'numFound' => isset($content['result_numFound']) ? $content['result_numFound']: null,
            'result_id' => isset($content['result_roid']) ? $content['result_roid'] : null,
            'result_group' => isset($content['result_group']) ? $content['result_group'] : null,
            'result_dsid' => isset($content['result_dsid']) ? $content['result_dsid'] : null,
            'record_owners' => $owners
        ];
    }

    /**
     * return if a given user agent is a bot
     * @param  string  $agent
     * @return boolean
     */
    private function isBot($agent)
    {
        if ($this->crawlerDetect->isCrawler($agent)
            || strpos($agent, 'uptimedoctor') > 0
        ) {
            return true;
        }

        return false;
    }

    /**
     * Ensure that the given directories are created and writable
     * @todo refactor to use symfony/filesystem component
     * @param  array $dirs list of directory
     * @return void
     */
    private function ensureDirectoryCreation($dirs)
    {
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                $this->debug($dir. ' does not exist Attempting to create');
                @mkdir($dir);
                @chmod($dir, 0755);
            }
        }
    }

    /**
     * Write the content to a file
     * @todo refactor to use symfony/filesystem component
     * @param  string $outputFilePath
     * @param  string $message
     * @return void
     */
    private function writeToFile($outputFilePath, $message)
    {
        if (file_exists($outputFilePath)) {
            $fh = fopen($outputFilePath, 'a');
            fwrite($fh, $message."\n");
        } else {
            $fh = fopen($outputFilePath, 'w');
            fwrite($fh, $message."\n");
        }
        fclose($fh);
    }

    /**
     * parse the content and check for registry object related fields
     * @param  array $content
     * @return array
     */
    private function parseRegistryObjectFields($content)
    {
        if (isset($content['roclass']) && !isset($content['class'])) {
            $content['class'] = $content['roclass'];
        }

        if (isset($content['dsid']) && !isset($content['data_source_id'])) {
            $content['data_source_id'] = $content['dsid'];
        }

        // terminate and return early if no roid is found in this event
        if (!array_key_exists('roid', $content)) {
            return $content;
        }

        /**
         * collect additional metadata from the database
         * @todo record_owner
         */
        if ($record = $this->db->getRecord($content['roid'])) {

            // additional metadata
            $fields = ['group', 'slug', 'data_source_id', 'group', 'key', 'type'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $record)) {
                    $content[$field] = $record[$field];
                } else {
                    $content['unknown_'.$field] = true;
                }
            }

            // record owners
            $content['record_owners'] = $this->db->getRecordOwners($record['data_source_id']);

        } else {
            $content['unknown'] = true;
        }

        return $content;
    }

    /**
     * parse fields that are splittable by a glue
     * @param  array $content
     * @return array
     */
    private function parseSplittableFields($content)
    {
        $splittableFields = ['result_roid', 'result_group', 'result_dsid'];
        foreach ($splittableFields as $field) {
            if (array_key_exists($field, $content)) {
                $content[$field] = explode(',,', $content[$field]);
            }
        }

        return $content;
    }

    /**
     * Prepare the dates
     * Used the InputInterface to determine the process date range
     * @param  array $dates
     * @return array
     */
    private function prepareDates($dates)
    {
        foreach ($dates as &$date) {
            $date = str_replace("log-portal-", "", $date);
            $date = str_replace(".php", "", $date);
        }
        $dates = array_values($dates);
        $this->debug('Removed the log-portal- prefix and .php affix');
        natsort($dates);
        $this->debug('Naturally sorted the dates');

        date_default_timezone_set('UTC');
        $dates = $this->date_range(reset($dates), end($dates), '+1day', 'Y-m-d');
        $this->debug('Constructed a date range between the first and last date by 1 day');
        $dates = array_reverse($dates);
        $this->debug('Reversed the dates');


        if ($from_date = $this->input->getOption('from_date')) {
            $dates = array_slice($dates, array_search($from_date, $dates));
            $this->debug('From Date: '. $from_date);
        }

        if ($to_date = $this->input->getOption('to_date')) {
            $dates = array_reverse($dates);
            $dates = array_slice($dates, array_search($to_date, $dates));
            $dates = array_reverse($dates);
            $this->debug('To Date: '. $to_date);
        }

        return $dates;
    }

    /**
     * Read a local file path and return the lines
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     * @param  string $file File Path Local
     * @return array()
     */
    private function readFileToLine($file)
    {
        $lines = array();
        if (file_exists($file)) {
            $file_handle = fopen($file, "r");
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                $lines[] = $line;
            }
            fclose($file_handle);
        }
        return $lines;
    }

    /**
     * Read a string in the form of [key:value]
     * and return an array of key=>value in PHP
     * @author  Minh Duc Nguyen <minh.nguyen@ands.org.au>
     * @param  string $string
     * @return array(key=>value)
     */
    public function readString($string)
    {
        $result = array();
        preg_match_all("/\[([^\]]*)\]/", $string, $matches);
        foreach ($matches[1] as $match) {
            $array = explode(':', $match, 2);
            if ($array && is_array($array) && isset($array[0]) && isset($array[1])) {
                $result[$array[0]] = $array[1];
            }
        }
        return $result;
    }

    /**
     * Creating date collection between two dates
     *
     * <code>
     * <?php
     * # Example 1
     * date_range("2014-01-01", "2014-01-20", "+1 day", "m/d/Y");
     *
     * # Example 2. you can use even time
     * date_range("01:00:00", "23:00:00", "+1 hour", "H:i:s");
     * </code>
     *
     * @author Ali OYGUR <alioygur@gmail.com>
     * @param string since any date, time or datetime format
     * @param string until any date, time or datetime format
     * @param string step
     * @param string date of output format
     * @return array
     */
    private function date_range($first, $last, $step = '+1 day', $output_format = 'd/m/Y')
    {
        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {
            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }

    /**
     * Read a directory and return a list of files
     * exclude the . and .. directory
     * @param  string $directory Directory Path Local
     * @return array
     */
    private function readDirectory($directory)
    {
        $scanned_directory = array_diff(scandir($directory), array('..', '.'));
        return $scanned_directory;
    }

    private function output($message)
    {
        $this->output->writeln($message);
    }

    private function debug($message)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $this->output->writeln("<comment>{$message}</comment>");
        }
    }

    private function verbose($message)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->output->writeln("<info>{$message}</info>");
        }
    }
}
