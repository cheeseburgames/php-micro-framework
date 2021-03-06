<?php
/**
 * Data validation Email field type class
 *
 * @author Christophe SAUVEUR <christophe@cheeseburgames.com>
 * @version 1.0
 * @package framework
 */
namespace CheeseBurgames\MFX\DataValidator\Field;

use CheeseBurgames\MFX\DataValidator\Field;
use CheeseBurgames\MFX\DataValidator\FieldType;
use CheeseBurgames\MFX\StringTools;

/**
 * Descriptor of an email field type
 */
class Email extends Field {

	/**
	 * (non-PHPdoc)
	 *
	 * @see Field::validate()
	 */
	public function validate($silent = false) {
		if (!parent::validate($silent))
			return false;

		if ($this->isRepeatable()) {
			$maxIndex = $this->getMaxRepeatIndex();
			for ($i = 0; $i <= $maxIndex; $i++) {
				$fieldValue = $this->getIndexedValue($i, true);
				if ($fieldValue !== NULL && !StringTools::isValidEmailAddress($fieldValue)) {
					if (empty($silent))
						trigger_error(sprintf(dgettext('mfx', "The field '%s' at index %d is not a valid email address."), $this->getName(), $i));
					return false;
				}
			}
		}
		else {
			$fieldValue = $this->getValue(true);
			if ($fieldValue !== NULL && !StringTools::isValidEmailAddress($fieldValue)) {
				if (empty($silent))
					trigger_error(sprintf(dgettext('mfx', "The field '%s' is not a valid email address."), $this->getName()));
				return false;
			}
		}
		return true;
	}

}

FieldType::registerClassForType(new FieldType(FieldType::EMAIL), Email::class);