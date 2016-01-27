<?php
/**
 * This file is part of ResMan library.
 *
 * Copyright (c) 2015 DTForce, s.r.o. (http://www.dtforce.com)
 *
 * For the full copyright and license information, please view
 * the file LICENSE that was distributed with this source code.
 */

namespace DTForce\ResMan;


final class Configuration
{

	/**
	 * @var string
	 */
	private $outputFolder;

	/**
	 * @var string
	 */
	private $namespace;

	/**
	 * @var string
	 */
	private $dir;


	/**
	 * @param string $outputFolder
	 * @param string $namespace
	 */
	public function __construct($outputFolder, $namespace, $dir)
	{
		$this->outputFolder = $outputFolder;
		$this->namespace = $namespace;
		$this->dir = $dir;
	}


	/**
	 * @return string
	 */
	public function getOutputFolder()
	{
		return $this->outputFolder;
	}


	/**
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->namespace;
	}


	/**
	 * @return string
	 */
	public function getDir()
	{
		return $this->dir;
	}

}
