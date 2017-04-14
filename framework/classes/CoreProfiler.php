<?php
/**
 * Profiling tool
 * 
 * @author Christophe SAUVEUR <christophe@cheeseburgames.com>
 * @version 1.0
 * @package framework
 */

namespace CheeseBurgames\MFX;

/**
 * Core profiler class
 */
class CoreProfiler
{
	/**
	 * @var CoreProfiler Singleton instance
	 */
	private static $_singleInstance = NULL;
	
	/**
	 * @var boolean Flag indicating if the class should fill the profiling data
	 */
	private $_ticking = true;
	/**
	 * @var float Profiling start time as returned by microtime(true);
	 */
	private $_profilingStartTime;
	/**
	 * @var float Profiling end time as returned by microtime(true);
	 */
	private $_profilingEndTime;
	/**
	 * @var array Profiling data
	 */
	private $_profilingData;
	/**
	 * @var int Last annotation index
	 */
	private $_lastAnnotation = 0;
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->_profilingStartTime = microtime(true);
		$this->_profilingEndTime = 0;
		$this->_profilingData = array();
	}
	
	/**
	 * Tick handler used for gathering profiling data
	 * @param string $event Custom event annotation to identify event times during profiling. If NULL, no event is provided (Defaults to NULL).
	 */
	public function tickHandler($event = NULL) {
		if ($this->_ticking || !empty($event))
			$this->_profilingData[] = array(
					microtime(true) - $this->_profilingStartTime, 
					memory_get_usage(), 
					memory_get_usage(true), 
					empty($event) ? 'null' : sprintf('"%d"', ++$this->_lastAnnotation), 
					empty($event) ? 'null' : sprintf('"%s"', $event)
			);
	}
	
	/**
	 * Initiliases profiling
	 * 
	 * This function enables output buffering.
	 */
	public static function init() {
		if (self::$_singleInstance !== NULL)
			return;
		
		self::$_singleInstance = new CoreProfiler();
		register_tick_function(array(&self::$_singleInstance, 'tickHandler'));
		Scripts::add('https://www.google.com/jsapi');
		ob_start();
		
		declare(ticks = 1);
	}
	
	/**
	 * Push a custom event into profiling data
	 * @param string $event Name of the even
	 */
	public static function pushEvent($event)
	{
		if (self::$_singleInstance === NULL || !empty(self::$_singleInstance->_profilingEndTime))
			return;
		
		self::$_singleInstance->_ticking = false;
		if (self::$_singleInstance !== NULL)
			self::$_singleInstance->tickHandler($event, true);
		self::$_singleInstance->_ticking = true;
	}
	
	/**
	 * Terminates profiling and output buffering and echoes the result
	 */
	public static function stop() {
		if (self::$_singleInstance === NULL)
			return;
		
		unregister_tick_function(array(&self::$_singleInstance, 'tickHandler'));
		self::$_singleInstance->_profilingEndTime = microtime(true);
		
		$peak = memory_get_usage();
		$realPeak = memory_get_peak_usage(true);
		
		$ini_output_buffering = ini_get('output_buffering');
		$minLevel = empty($ini_output_buffering) ? 1 : 2;
		while (ob_get_level() > $minLevel)
			ob_end_flush();
		$buffer = ob_get_contents();
		ob_clean();
		
		$memlimit = ini_get('memory_limit');
		$regs = NULL;
		preg_match('/^([1-9]\d*)([kmg])?$/i', $memlimit, $regs);
		if (!empty($regs[2]))
		{
			switch ($regs[2])
			{
				case 'k':
				case 'K':
					$memlimit = $regs[1] * 1024;
					break;
				case 'm':
				case 'M':
					$memlimit = $regs[1] * pow(1024, 2);
					break;
				case 'g':
				case 'G':
					$memlimit = $regs[1] * pow(1024, 3);
					break;
			}
		} 
		
		$context = array(
				'duration' => self::getProfilingDuration(),
				'opCount' => count(self::$_singleInstance->_profilingData),
				'memPeakUsage' => $peak,
				'memPeakUsageRatio' => $peak / $memlimit,
				'memRealPeakUsage' => $realPeak,
				'memRealPeakUsageRatio' => $realPeak / $memlimit,
				'data' => self::$_singleInstance->_profilingData
		);
		$str = $GLOBALS['twig']->render('@mfx/Profiler.twig', $context);
		
		echo preg_replace('/<!--\s+--MFX_PROFILING_OUTPUT--\s+-->/', $str, $buffer);
	}
	
	/**
	 * Evaluates how long was the profiling
	 * @return boolean|float false if profiling is not initialized or complete, or the duration in milliseconds
	 */
	public static function getProfilingDuration() {
		if (self::$_singleInstance === NULL || empty(self::$_singleInstance->_profilingEndTime))
			return false;
		
		return (self::$_singleInstance->_profilingEndTime - self::$_singleInstance->_profilingStartTime);
	}
}