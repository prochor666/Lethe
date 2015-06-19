<?php
/**
* Lethe framework commandline
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/
namespace Lethe;

/**
* Lethe\Cmd - Lethe framework commandline 
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-04-28)
*/
class Cmd extends Lethe{

	public $command, $options;

	private $foregroundColors, $backgroundColors;

	/** 
	* @ignore
	*/
	public function __construct(){
		parent::__construct();

		// Set up shell colors
		$this->foregroundColors['black'] = '0;30';
		$this->foregroundColors['dark_gray'] = '1;30';
		$this->foregroundColors['blue'] = '0;34';
		$this->foregroundColors['light_blue'] = '1;34';
		$this->foregroundColors['green'] = '0;32';
		$this->foregroundColors['light_green'] = '1;32';
		$this->foregroundColors['cyan'] = '0;36';
		$this->foregroundColors['light_cyan'] = '1;36';
		$this->foregroundColors['red'] = '0;31';
		$this->foregroundColors['light_red'] = '1;31';
		$this->foregroundColors['purple'] = '0;35';
		$this->foregroundColors['light_purple'] = '1;35';
		$this->foregroundColors['brown'] = '0;33';
		$this->foregroundColors['yellow'] = '1;33';
		$this->foregroundColors['light_gray'] = '0;37';
		$this->foregroundColors['white'] = '1;37';

		$this->backgroundColors['black'] = '40';
		$this->backgroundColors['red'] = '41';
		$this->backgroundColors['green'] = '42';
		$this->backgroundColors['yellow'] = '43';
		$this->backgroundColors['blue'] = '44';
		$this->backgroundColors['magenta'] = '45';
		$this->backgroundColors['cyan'] = '46';
		$this->backgroundColors['light_gray'] = '47';

		$this->options = array();
		$this->command = 'info';
	}

	public function run(){
		
		if( method_exists($this, $this->command) && is_callable(array($this, $this->command)) ){
			return call_user_func_array(array($this, $this->command), $this->options);
		}

		if( function_exists($this->command) ){
			return call_user_func_array($this->command, $this->options);
		}

		if( method_exists($this, $this->command) && is_callable(array('self', $this->command)) ){
			return 'Lethe error: command '.$this->getColored($this->command, 'light_red').' not found'.PHP_EOL;
			//return call_user_func_array(self::$this->command, $this->options);
		}

		return 'Lethe error: command '.$this->getColored($this->command, 'light_red').' not found'.PHP_EOL;
	}

	public function info(){
		$info = 'Lethe environment: '.$this->getColored(PHP_OS.'/'.PHP_SAPI.'', 'light_green').PHP_EOL
				.'Lethe version: '.$this->getColored($this->config('system/version'), 'light_green').PHP_EOL
				.'Lethe codename: '.$this->getColored($this->config('system/productCodename'), 'light_green').PHP_EOL;

		return $info;
	}

	public function conf(){
		$info = $this->getColored(Tools::dump($this->config('system')), 'light_green').PHP_EOL;

		return $info;
	}

	public function registry(){
		$info = $this->getColored(Tools::dump(Reg::read()), 'yellow').PHP_EOL;

		return $info;
	}
	
	// Returns colored string
	public function getColored($string, $foregroundColor = null, $backgroundColor = null) {
		$coloredString = "";

		// Check if given foreground color found
		if (isset($this->foregroundColors[$foregroundColor])) {
			$coloredString .= "\033[" . $this->foregroundColors[$foregroundColor] . "m";
		}
		// Check if given background color found
		if (isset($this->backgroundColors[$backgroundColor])) {
			$coloredString .= "\033[" . $this->backgroundColors[$backgroundColor] . "m";
		}

		// Add string and end coloring
		$coloredString .=  $string . "\033[0m";

		return $coloredString;
	}

	// Returns all foreground color names
	public function getForegroundColors() {
		return array_keys($this->foregroundColors);
	}

	// Returns all background color names
	public function getBackgroundColors() {
		return array_keys($this->backgroundColors);
	}

}

