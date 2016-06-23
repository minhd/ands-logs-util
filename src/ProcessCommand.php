<?php

namespace MinhD\ANDSLogUtil;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('from_dir', null, InputOption::VALUE_OPTIONAL, "Set the from directory", 'default_input_dir')
            ->addOption('to_dir', null, InputOption::VALUE_OPTIONAL, "Set the to directory", 'default_output_dir')
            ->addOption('from_date', null, InputOption::VALUE_OPTIONAL, "Set the to directory", 'default_output_dir')
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

        // read the from directory for dates
        $fromDir = '/Users/mnguyen/dev/elk/logs/'.$logType;
        $this->verbose('Processing directory: '. $fromDir);

        $dates = $this->readDirectory($fromDir);
        $this->debug('Found '.count($dates). ' dates for processing');


        // option: fromDate in desc order
        // @todo

        // order the dates
        foreach ($dates as &$date) {
            $date = str_replace("log-portal-", "", $date);
            $date = str_replace(".php", "", $date);
        }
        $dates = array_values($dates);
        $this->debug('Removed the log-portal- prefix and .php affix');

        natsort($dates);
        $this->debug('Naturally sorted the dates');

        // Clean Up
        date_default_timezone_set('UTC');
        $dates = $this->date_range(reset($dates), end($dates), '+1day', 'Y-m-d');
        $this->debug('Constructed a date range between the first and last date by 1 day');
        $dates = array_reverse($dates);
        $this->debug('Reversed the dates');

        if ($date_from) {
            $key = array_search($date_from, $dates);
            $dates = array_slice($dates, $key);
        }

        $this->verbose('Processing '.count($dates). ' dates starting with '.reset($dates). ' ends with '.end($dates));

        // Process the logs
        foreach ($dates as $date) {
            $this->processLogFile($date);
        }

        // foreach date read the lines

        // construct a file to store the date result
        // for each line, process them into content
        // fill up the content with relevant data
        //

        $this->output('Done!');
    }

    private function processLogFile($date)
    {
        $this->debug('Processing '. $date);
        $filePath = $fromDir.'/log-'.$logType.'-'.$date.'.php';
        $this->debug('File path: '. $filePath);
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
