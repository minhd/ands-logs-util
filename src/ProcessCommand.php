<?php

namespace MinhD\ANDSLogUtil;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * MinhD\ANDSLogUtil\ProcessCommand
 * Usage: php ands-log process --from_date --to_date --from_dir --to_dir
 */
class ProcessCommand extends Command
{

    protected $input;
    protected $output;

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

        // option: log type
        $logType = $input->getArgument('type');
        $fromDir = $input->getOption('from_dir');
        $toDir = $input->getOption('to_dir');

        // read the from directory for dates
        $fromDir = $fromDir.$logType;
        $this->verbose('Processing directory: '. $fromDir);
        $dates = $this->readDirectory($fromDir);
        $this->debug('Found '.count($dates). ' dates for processing');

        // prepare dates array with optional from and to
        $dates = $this->prepareDates($dates, $input);

        $this->verbose('Processing '.count($dates). ' dates starting with '.reset($dates). ' ends with '.end($dates));

        // Process the logs
        foreach ($dates as $date) {
            $this->processLogFile($date, $fromDir, $logType, $toDir);
        }

        $this->output('Done!');
    }

    private function processLogFile($date, $fromDir, $logType, $toDir)
    {
        $this->debug('Processing '. $date);
        $filePath = $fromDir.'/log-'.$logType.'-'.$date.'.php';
        $this->debug('File path: '. $filePath);

        $lines = $this->readFileToLine($filePath);
        $this->debug('Lines count: '. count($lines));

        $this->ensureDirectoryCreation([$toDir, $toDir.$logType]);

        $outputFilePath = $toDir.$logType.'/'.$date.'.log';

        $CrawlerDetect = new CrawlerDetect();

        foreach ($lines as $line) {
            $content = $this->readString($line);

            // does not convert empty event or event without date
            if (!$content || count($content) === 0 || !array_key_exists('date', $content)) {
                continue;
            }

            // does not convert bot events
            $content['user_agent'] = array_key_exists('user_agent', $content) ? $content['user_agent'] : 'dude';
            $content['is_bot'] = $CrawlerDetect->isCrawler($content['user_agent']) ? true : false;
            if ($content['is_bot']) {
                continue;
            }

            // construct the event
            $event = array_key_exists('event', $content) ? $content['event'] : 'unknown_event';
            $parsed = [
                '@timestamp' => date("c", strtotime($content['date'])),
                '@source' => 'localhost',
                '@message' => $event,
                '@tags' => [$logType, $event],
                '@type' => $logType,
                'channel' => $logType,
                'level' => 200
            ];

            // fix/add registryObject data
            $content = $this->parseRegistryObjectFields($content);

            // parse splittable fields logic
            $content = $this->parseSplittableFields($content);

            // parse data after content is alright
            foreach ($content as $key=>$value) {
                $parsed['@fields']['ctxt_'.$key] = $value;
            }

            $message = json_encode($parsed);

            $this->writeToFile($outputFilePath, $message);

            // $this->debug($message);
            unset($content);
            unset($parsed);
        }
        $this->verbose('Finished '.$date);
    }

    private function ensureDirectoryCreation($dirs)
    {
        foreach ($dirs as $dir) {
            $this->debug($dir. ' does not exist Attempting to create');
            @mkdir($dir);
            @chmod($dir, 0755);
        }
    }

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

    private function parseRegistryObjectFields($content)
    {
        if (isset($content['roclass']) && !isset($content['class'])) {
            $content['class'] = $content['roclass'];
        }

        if (isset($content['dsid']) && !isset($content['data_source_id'])) {
            $content['data_source_id'] = $content['dsid'];
        }
        return $content;
    }

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
    private function readString($string)
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
