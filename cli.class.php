#!/usr/bin/env php
<?php

	/****************************************************************/
	/* Moody                                                        */
	/* cli.class.php                                     			*/
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody;

	/**
	 * Command line interface for Moody
	 */
	class CLI  {
		private $command = null;
		private $benchmark = false;
		private $inExecute = false;

		private function printUsage() {
			echo <<<HELP_END
Moody CLI interpreter
Usage: {$this->command} [OPTIONS...] [-o <output>] [-m] [--] [<input>] [<output>]

Process a Moody/PHP file and apply minification and other optimizations to
generate PHP code.

Moody can parse PHP files using syntax features up to PHP 5.4. While Moody is
usually used as a preprocessor for PHP files, it can also be used standalone as
an interpreted language. See -m.

When no file is given, read Moody/PHP code from standard input. In this case
input is not available to Moody instructions. Note that code cannot be streamed
in; the input is read fully before parsing and executing.

Generated PHP code is written to standard output or a file (if -o <output> is
given). Note that standard output may also contain text printed by Moody
instructions. For backwards compatibility, output file can also be given as
second argument.

Options:
  -m                    Enable Moody interpreter mode. Implies --silent.
                        This sets -C requireinstructiondot=false so Moody
                        instructions do not need the preceeding dot. Enabled
                        automatically when input is a file and filename
                        extension is "moody" or "mdy".
  -o <output>           Write generated code to <output> instead of standard
                        output. Convention is to use "cphp" extension for
                        generated files.
  --benchmark           Print time used to process file.
  --silent              Do not print generated code to standard output. No
                        effect when -o is given.
  --dump                Not used, but accepted for backwards compatibility.

  -h, -H, --help        Print help and exit.
  -V, --version         Print version and exit.

Moody VM options:
  -C <config>=<value>   Set VM configuration <config> to <value>. Only valid
                        values are "true" and "false". Note that Moody code can
						override configuration.
  -O[<level>]           Optimize generated code. These are aliases for certain
                        VM configurations, where each level enables additional
                        optimizations, which are listed below. Applied before
                        individual -C options.
                          -O0: No optimizations (This is the default)
                          -O1, -O: Delete whitespace, comments and phpdoc
                               -C deletewhitespaces=true
                               -C deletecomments=true
                          -O2: Also substitute symbols
                               -C autosubstitutesymbols=true
                          -O3: Also rename all variables
                               -C compressvariables=true
                               -C compressproperties=true
                               This is potentially destructive; generated code
                               may no longer work correctly if included into
                               another PHP script.

HELP_END;
		}

		private function usageError($msg) {
			fwrite(\STDERR, $msg . \PHP_EOL);
			exit(2);
		}
		
		public function main($argv) {
			set_error_handler(array($this, 'errorHandler'));
			
			$this->command = $argv[0];
			$opts = getopt('mo:hHVC:O::', array('benchmark', 'silent', 'dump', 'version', 'help'), $optind);
			$args = array_slice($argv, $optind);

			if($opts === false) {
				$this->printUsage();
				$this->usageError("Invalid parameters.");
			} else if(isset($opts['h']) || isset($opts['H']) || isset($opts['help'])) {
				$this->printUsage();
				exit;
			}

			/* Load moody.cphp */
			if(!file_exists('moody.cphp')) {
				if(file_exists('build.php') && is_readable('build.php')) {
					echo "moody.cphp not found. Build from source now? (y/n) ";
					if(strtolower(fread(\STDIN, 1)) != 'y') {
						echo "Aborting." . \PHP_EOL;
						exit;
					} else {
						require_once 'build.php';
						
						/* Do not load moody.cphp as build.php already has included the Moody sources */
						goto loaded;
					}
				} else {
					echo "moody.cphp not found." . \PHP_EOL;
					exit;
				}
			}
			
			if(!is_readable('moody.cphp')) {
				echo "moody.cphp exists but is not readable." . \PHP_EOL;
				exit;
			}
			
			require_once 'moody.cphp';
			
			loaded:
			
			/* Load token handlers */
			foreach(get_declared_classes() as $class) {
				if(in_array('Moody\TokenHandler', class_implements($class)))
					$class::getInstance();
			}

			/* Parse arguments */
			if(isset($opts['V']) || isset($opts['version'])) {
				echo "Moody CLI Interpreter v" . MOODY_VERSION . \PHP_EOL;
				echo "2012 Yussuf Khalil" . \PHP_EOL;
				exit;
			}

			$moodymode = isset($opts['m']);
			$this->benchmark = isset($opts['benchmark']);

			if(isset($opts['o']))
				if(!is_string($opts['o']) || !strlen($opts['o']))
					$this->usageError("Invalid parameter -o.");
				else
					$destinationFile = $opts['o'];

			foreach($args as $arg) {
				if(!isset($executeFile))
					$executeFile = $arg;
				else if(!isset($destinationFile))
					$destinationFile = $arg;
				else
					$this->usageError("Trailing argument.");
			}

			/* Apply VM configuration */
			Configuration::set('autosubstitutesymbols', false);

			if(isset($opts['O'])) {
				if($opts['O'] === false)
					$opts['O'] = '1';

				switch($opts['O']) {
					case '3':
						Configuration::set('compressvariables', true);
						Configuration::set('compressproperties', true);
					case '2':
						Configuration::set('autosubstitutesymbols', true);
					case '1':
						Configuration::set('deletewhitespaces', true);
						Configuration::set('deletecomments', true);
					case '0':
						break;
					default:
						$this->usageError("Invalid parameter -O.");
				}
			}

			if(isset($opts['C'])) {
				$configs = is_array($opts['C']) ? $opts['C'] : array($opts['C']);

				foreach($configs as $config) {
					if(!preg_match('~([a-z]+)=(true|false)$~i', $config, $matches))
						$this->usageError("Invalid parameter -C.");

					Configuration::set($matches[1], (bool) $matches[2]);
				}
			}

			/* Load and execute file */
			if(isset($executeFile)) {
				if(!file_exists($executeFile))
					$this->usageError("File does not exist.");
				if(!is_readable($executeFile))
					$this->usageError("File is not readable.");

				if(substr($executeFile, -4) == '.mdy' || substr($executeFile, -6) == '.moody')
					$moodymode = true;

				$file = file_get_contents($executeFile);
			} else {
				$file = stream_get_contents(\STDIN);
				fclose(\STDIN);
			}

			if($moodymode) {
				Configuration::set("requireinstructiondot", false);
				/* Satisfy the tokenizer */
				$file = '<?php ' . $file . ' ?>';
			}

			$source = $this->executeSource($file, isset($executeFile) ? $executeFile : 'Standard Input', isset($executeFile));
			
			if($source === false)
				exit(1);

			if(isset($destinationFile)) {
				if(!file_put_contents($destinationFile, $source))
					$this->usageError("Failed to write to output file.");
			} else if(!isset($opts['silent']) && !$moodymode)
				echo $source;
		}
		
		public function executeSource($source, $origin = "Unknown", $appendT_EOF = false) {
			$tokenArray = Token::tokenize($source, $origin);
			if($appendT_EOF) {
				$token = new Token;
				$token->type = T_EOF;
				$token->fileName = $origin;
				$tokenArray[] = $token;
			}
			$source = $this->executeScript($tokenArray);
			
			if(is_array($source)) {
				$sourceString = "";
				
				foreach($source as $token)
					$sourceString .= $token->content;
				
				return $sourceString;
			}
			
			return $source;
		}
		
		public function executeScript($tokenArray) {
			try {
				ConstantContainer::initialize();
				
				$vm = new TokenVM;
				
				$this->inExecute = true;
				
				if($this->benchmark)
					$timeStart = microtime(true);
				
				$tokenArray = $vm->execute($tokenArray);
				
				if($this->benchmark)
					echo "Script execution took " . ((microtime(true) - $timeStart) * 1000) . " ms." . \PHP_EOL;
				
				$this->inExecute = false;
				return $tokenArray;
			} catch(\Exception $exception) {
				if($this->benchmark)
					$executionTime = (microtime(true) - $timeStart) * 1000;
				
				echo (string) $exception . \PHP_EOL;
				
				if($this->benchmark)
					echo "Script execution took " . $executionTime . " ms." . \PHP_EOL;
				
				$this->inExecute = false;
				return false;
			}
		}
		
		public function errorHandler($errType, $errStr, $errFile, $errLine) {
			if($errType == E_DEPRECATED || $errType == E_STRICT || $errType == E_NOTICE)
				return false;

			if($this->inExecute)
				throw new MoodyException($errStr, $errType);
			
			throw new \ErrorException($errStr, 0, $errType, $errFile, $errLine);
		}
	}

	$cli = new CLI;
	$cli->main($argv);
?>
