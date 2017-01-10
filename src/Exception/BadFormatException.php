<?php
/**
 * This file is part of ResMan library.
 *
 * Copyright (c) 2015 DTForce, s.r.o. (http://www.dtforce.com)
 *
 * For the full copyright and license information, please view
 * the file LICENSE that was distributed with this source code.
 */


namespace dtforce\resman\src\Exception;

use Exception;


class BadFormatException extends Exception
{

	/**
	 * @param string filePath
	 * @param int $line
	 */
	public function __construct($filePath, $line)
	{
		$this->message = "$line. line in $filePath has bad format.";
	}

}
