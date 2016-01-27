<?php
/**
 * This file is part of ResMan library.
 *
 * Copyright (c) 2015 DTForce, s.r.o. (http://www.dtforce.com)
 *
 * For the full copyright and license information, please view
 * the file LICENSE that was distributed with this source code.
 */

namespace DTForce\ResMan\Exception;

use Exception;


final class UndefinedKeysFoundException extends Exception
{

	/**
	 * @var string[]
	 */
	private $keys;

	/**
	 * @var string
	 */
	private $version;


	/**
	 * @param string[] $keys
	 * @param string $version
	 */
	public function __construct(array $keys, $version)
	{
		$this->version = $version;
		$this->keys = $keys;
		parent::__construct(
			sprintf(
				"Keys found in version (%s) that were not defined in default version: %s",
				$version, implode(',', $keys)
			)
		);
	}


	/**
	 * @return string[]
	 */
	public function getKeys()
	{
		return $this->keys;
	}


	/**
	 * @return string
	 */
	public function getVersion()
	{
		return $this->version;
	}

}
