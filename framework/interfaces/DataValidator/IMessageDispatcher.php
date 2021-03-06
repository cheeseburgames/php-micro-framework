<?php
/**
 * Data validator message dispatcher interface
 * 
 * @author Christophe SAUVEUR <christophe@cheeseburgames.com>
 * @version 1.0
 * @package framework
 */

namespace CheeseBurgames\MFX\DataValidator;

/**
 * Interface describing data validator's message dispatchers
 */
interface IMessageDispatcher {
	
	/**
	 * Dispatches a message
	 * @param string $message Message to dispatch
	 * @param int $level Error level of the message to dispatch
	 */
	function dispatchMessage($message, $level);
	
}