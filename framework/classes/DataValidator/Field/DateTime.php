<?php
namespace CheeseBurgames\MFX\DataValidator\Field;

use CheeseBurgames\MFX\DataValidator\Field;
use CheeseBurgames\MFX\DataValidator\FieldType;

class DateTime extends Field {

	/**
	 * Constructor
	 * @param string $name Field's name
	 * @param FieldType $type Field's type
	 * @param mixed $defaultValue Field's default value
	 * @param boolean $required If set, this field will become required in the validation process.
	 */
	protected function __construct($name, FieldType $type, $defaultValue, $required) {
		parent::__construct($name, $type, empty($defaultValue) ? 0 : $defaultValue, $required);
	}

	/**
	 * (non-PHPdoc)
	 * @see Field::validate()
	 */
	public function validate($silent = false) {
		if (!parent::validate($silent))
			return false;

		$re = sprintf('#^%s$#', self::regexPattern($this->getType()));
		switch ($this->getType()->value()) {
			case FieldType::DATE:
				$error = dgettext('mfx', "The field '%s' does not contain a valid date.");
				$errorRepeatable = dgettext('mfx', "The field '%s' at index %d does not contain a valid date.");
				break;
			case FieldType::TIME:
				$error = dgettext('mfx', "The field '%s' does not contain a valid time.");
				$errorRepeatable = dgettext('mfx', "The field '%s' at index %d does not contain a valid time.");
				break;
		}

		if ($this->isRepeatable())
		{
			$maxIndex = $this->getMaxRepeatIndex();
			for ($i = 0; $i <= $maxIndex; $i++)
			{
				if (!preg_match($re, $this->getIndexedValue($i, true)))
				{
					if (empty($silent))
						trigger_error(sprintf($errorRepeatable, $this->getName(), $i));
					return false;
				}
			}
		}
		else
		{
			if (!preg_match($re, $this->getValue(true)))
			{
				if (empty($silent))
					trigger_error(sprintf($error, $this->getName()));
				return false;
			}
		}

		return true;
	}

	/**
	 * (non-PHPdoc)
	 * @see Field::generate()
	 * @param array $containingGroups
	 * @param FieldType $type_override
	 */
	public function generate(array $containingGroups = array(), FieldType $type_override = NULL) {
		$result = parent::generate($containingGroups, $type_override);
		$result[1]['suffix'] = self::humanlyReadablePattern($this->getType());
		return $result;
	}

	/**
	 * Gets the pattern to use with the date() function
	 * @param FieldType $type Type of the field
	 * @return string
	 */
	public static function dateFunctionPattern(FieldType $type) {
		return $type->equals(FieldType::DATE) ? 'Y-m-d' : 'H:i';
	}

	/**
	 * Gets the pattern as humanly readable
	 * @param FieldType $type Type of the field
	 * @return string
	 */
	public static function humanlyReadablePattern(FieldType $type) {
		return $type->equals(FieldType::DATE) ? dgettext('mfx', 'mm/dd/yyyy') : dgettext('mfx', 'hh:mm');
	}

	/**
	 * Gets the pattern as a regular expression
	 * @param FieldType $type Type of the field
	 * @param boolean $withBackReferences If set, the function should return a regular expression pattern containing name back references
	 * @return string
	 */
	public static function regexPattern(FieldType $type, $withBackReferences = false) {
		if (empty($withBackReferences))
			return $type->equals(FieldType::DATE) ? '\d{4}-(0\d|1[0-2])-([0-2]\d|3[01])' : '([01]\d|2[0-3]):[0-5]\d';
		else
			return $type->equals(FieldType::DATE) ? '(?<year>\d{4})-(?<month>0\d|1[0-2])-(?<day>[0-2]\d|3[01])' : '(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d)';
	}
}

FieldType::registerClassForType(new FieldType(FieldType::DATE), DateTime::class);
FieldType::registerClassForType(new FieldType(FieldType::TIME), DateTime::class);