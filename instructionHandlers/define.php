<?php

	/****************************************************************/
	/* Moody                                                        */
	/* define.php                 					                */
	/* 2012 Yussuf Khalil                                           */
	/****************************************************************/
	
	namespace Moody\InstructionHandlers {
	
	use Moody\DefaultInstructionHandler;
	use Moody\ConstantContainer;
	use Moody\InstructionHandler;
	use Moody\Token;
	use Moody\TokenHandlers\InstructionProcessor;
	use Moody\TokenVM;
	use Moody\InstructionProcessorException;
	
	class DefineHandler implements InstructionHandler, DefaultInstructionHandler {
		private static $instance = null;
		
		private function __construct() {
			InstructionProcessor::getInstance()->registerHandler('define', $this);
			InstructionProcessor::getInstance()->registerHandler('def', $this);
			InstructionProcessor::getInstance()->registerHandler('d', $this);
			InstructionProcessor::getInstance()->registerDefaultHandler($this);
		}
		
		public static function getInstance() {
			if(!self::$instance)
				self::$instance = new self;
			return self::$instance;
		}

		public function execute(Token $token, $instructionName, InstructionProcessor $processor, TokenVM $vm = null, $executionType = 0) {
			if($executionType & InstructionProcessor::EXECUTE_TYPE_DEFAULT) {
				$args = $processor->parseArguments($token, $instructionName, 'sx');
				$constantName = substr($instructionName, 1);
				switch($args[0]) {
					case '=':
						ConstantContainer::define($constantName, $args[1]);
						break;
					case '.=':
						ConstantContainer::define($constantName, ConstantContainer::getConstant($constantName) . $args[1]);
						break;
					case '+=':
					case '-=':
					case '*=':
					case '/=':
					case '|=':
					case '&=':
					case '^=':
						$validOperators = array('(', ')', '+', '-', '*', '/', '|', '&', '^');
						$args = $processor->parseArguments($token, $instructionName, 'sn?snsnsnsnsnsnsnsnsnsnsn');
						foreach($args as $index => $arg) {
							if(!$index)
								continue;
							if(!is_int($arg) && !in_array($arg, $validOperators))
								throw new InstructionProcessorException('Math syntax error');
							$calc .= $arg;
						}
						
						if(($value = eval('return (' . $calc . ');')) === false)
							throw new InstructionProcessorException('Math syntax error');
						
						switch($args[0]) {
							case '+=':
								ConstantContainer::define($constantName, ConstantContainer::getConstant($constantName) + $value);
								break;
							case '-=':
								ConstantContainer::define($constantName, ConstantContainer::getConstant($constantName) - $value);
								break;
							case '*=':
								ConstantContainer::define($constantName, ConstantContainer::getConstant($constantName) * $value);
								break;
							case '/=':
								ConstantContainer::define($constantName, ConstantContainer::getConstant($constantName) / $value);
								break;
							case '|=':
								ConstantContainer::define($constantName, ConstantContainer::getConstant($constantName) | $value);
								break;
							case '^=':
								ConstantContainer::define($constantName, ConstantContainer::getConstant($constantName) ^ $value);
								break;
							case '&=':
								ConstantContainer::define($constantName, ConstantContainer::getConstant($constantName) & $value);
								break;
						}
				}
			} else {
				$args = $processor->parseArguments($token, $instructionName, 'sx');
				ConstantContainer::define($args[0], $args[1]);
			}
			
			return TokenVM::DELETE_TOKEN;
		}

	 	public function canExecute(Token $token, $instructionName, InstructionProcessor $processor) {
	 		$args = $processor->parseArguments($token, $instructionName, '');
	 		
	 		$validOperators = array('=', '.=', '+=', '-=', '*=', '/=', '|=', '&=', '^=');
	 		
	 		if(in_array($args[0], $validOperators) && !($args[0] != '=' && !ConstantContainer::isDefined(substr($instructionName, 1))))
	 			return true;
	 		return false;
		}
	}
	
	}
?>