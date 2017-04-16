<?php
/**
 * XML tools
 * 
 * @author Christophe SAUVEUR <christophe@cheeseburgames.com>
 * @version 1.0
 * @package framework
 */

namespace CheeseBurgames\MFX;

/**
 * Class containing utility functions for encoding data in XML
 */
class XMLTools
{
	/**
	 * @var string Stores the string representation of PHP_INT_MAX
	 */
	private static $PHP_INT_MAX_AS_STR = NULL;
	/**
	 * @var int Stores the string representation length of PHP_INT_MAX
	 */
	private static $PHP_INT_MAX_LENGTH = 0;
	/**
	 * @var array Container for object references used to avoid recursions
	 */
	private static $RECURSIONS;
	
	/**
	 * Write XML tree from a variable
	 *
	 * For objects, the function iterates only on public properties.
	 *
	 * @param mixed $var Value to write as XML
	 * @param boolean $filterStrings If set, the strings are filtered to the native primitive type if applying. (Defaults to true)
	 *
	 * @used-by XMLTools::build()
	 */
	private static function _build(\XMLWriter $writer, $var, $filterStrings = true)
	{
		// NULL
		if (is_null($var))
			$writer->writeElement('null');
		// Scalar values
		else if (is_scalar($var))
		{
			if (is_string($var))
			{
				$regs = NULL;
				
				// Booleans as string
				if ($filterStrings && preg_match('/^(true|false)$/', $var))
					$writer->writeElement('bool', $var);
				// Integers as string
				else if ($filterStrings && preg_match('/^-?([1-9]\d*)$/', $var, $regs))
				{
					if (self::$PHP_INT_MAX_AS_STR === NULL)
					{
						self::$PHP_INT_MAX_AS_STR = strval(PHP_INT_MAX);
						self::$PHP_INT_MAX_LENGTH = strlen(self::$PHP_INT_MAX_AS_STR);
					}
	
					$length = strlen($regs[1]);
					if ($length < self::$PHP_INT_MAX_LENGTH || ($length == self::$PHP_INT_MAX_LENGTH && strcmp(self::$PHP_INT_MAX_AS_STR, $regs[1]) >= 0))
						$writer->writeElement('int', $var);
					else
					{
						$writer->startElement('string');
						$writer->writeCdata($var);
						$writer->endElement();
					}
				}
				else if ($filterStrings && preg_match('/^[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?$/', $var))
					$writer->writeElement('float', $var);
				else
				{
					$writer->startElement('string');
					$writer->writeCdata($var);
					$writer->endElement();
				}
			}
			else if (is_int($var))
				$writer->writeElement('int', strval($var));
			else if (is_float($var))
				$writer->writeElement('float', strval($var));
			else if (is_bool($var))
				$writer->writeElement('bool', $var ? 'true' : 'false');
		}
		// Arrays
		else if (is_array($var))
		{
			$writer->startElement('array');
			foreach ($var as $k => $v) {
				$writer->startElement('key');
				$writer->writeCdata(strval($k));
				$writer->endElement();
				self::_build($writer, $v, $filterStrings);
			}
			$writer->endElement();
		}
		// Objects
		else if (is_object($var))
		{
			// Check for recursions
			if (in_array($var, self::$RECURSIONS))
			{
				$writer->startElement('object');
				$writer->writeAttribute('recursion', 'true');
				$writer->endElement();
				return;
			}
			self::$RECURSIONS[] = $var;
			
			$filterStrings = ($filterStrings && $var instanceof IUnfilteredSerializable == false);
			
			$ro = new \ReflectionObject($var);
			$writer->startElement('object');
			if ($var instanceof stdClass == false)
				$writer->writeAttribute('class', $ro->name);
			$props = $ro->getProperties(\ReflectionProperty::IS_PUBLIC);
			foreach ($props as $v)
			{
				$writer->startElement('prop');
				$writer->writeCdata($v->getName());
				$writer->endElement();
				self::_build($writer, $v->getValue($var), $filterStrings);
			}
			$writer->endElement();
			
			array_pop(self::$RECURSIONS);
		}
	}
	
	/**
	 * Build XML tree from a variable
	 * @param mixed $var Variable from which building the XML tree
	 * @param string $encoding Encoding charset (Defaults to UTF-8). 
	 * @return string the XML tree string
	 */
	public static function build($var, $encoding = 'UTF-8') {
		self::$RECURSIONS = array();
		
		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(true);
		$writer->setIndentString("\t");
		$writer->startDocument('1.0', $encoding);
		self::_build($writer, $var);
		$writer->endDocument();
		return $writer->outputMemory(false);
	}
}