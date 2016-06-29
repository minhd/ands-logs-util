# ANDS Log Utilities
A set of utilities software to deal with ANDS logging infrastructure

## Process Legacy Log
The purpose is to convert logs from legacy format to the new Monolog and Logstash compatible format to be used with the ELK stack

### Usage
```
./ands-log help process
./ands-log process portal
```
Arguments

Usage `php ands-log process {arg}`

* type, default to portal

Options:

Usage `php ands-log process portal --{key}={value}`

* from_dir
* to_dir
* from_date
* to_date
* help
* quiet
* version
* ansi
* no-ansi
* no-interaction
* v|vv|vvv: Verbose, 1 for normal output, 2 for verbose, 3 for debug