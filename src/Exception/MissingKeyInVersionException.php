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


final class MissingKeyInVersionException extends Exception
{

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var string
	 */
	private $key;


	/**
	 * @param string $key
	 * @param string $version
	 */
	public function __construct($version, $key)
	{
		$this->version = $version;
		$this->key = $key;
		parent::__construct(sprintf("Value key (%s) missing in version: %s", $key, $version));
	}


	/**
	 * @return string
	 */
	public function getVersion()
	{
		return $this->version;
	}


	/**
	 * @return string
	 */
	public function getKey()
	{
		return $this->key;
	}

}
