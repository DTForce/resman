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

use DTForce\ResMan\Generator\ConstantGenerator;
use DTForce\ResMan\Generator\ValuesGenerator;
use Nette\Neon\Neon;
use Zend\Code\Generator\ValueGenerator;


final class ResMan
{

	/**
	 * @var Configuration
	 */
	private $configuration;

	/**
	 * @var ConstantGenerator
	 */
	private $constantGenerator;

	/**
	 * @var ValueGenerator
	 */
	private $valueGenerator;

	/**
	 * @var string[]
	 */
	private $constants;

	/**
	 * @var string[]
	 */
	private $values;


	/**
	 * @param string $file
	 */
	public function __construct($file)
	{
		$this->configuration = $this->readConfiguration($file);
		$this->constantGenerator = new ConstantGenerator($this->configuration);
		$this->valueGenerator = new ValuesGenerator($this->configuration, $this->constantGenerator);
	}


	public function updateGeneratedResources()
	{
		foreach ($this->constants as $value) {
			$this->constantGenerator->generateFromNeonFile($this->configuration->getDir() . DIRECTORY_SEPARATOR . $value);
		}
		foreach ($this->values as $value) {
			$this->valueGenerator->generateFromNeonFile($this->configuration->getDir() . DIRECTORY_SEPARATOR . $value);
		}
	}


	/**
	 * @param string $file
	 *
	 * @return Configuration
	 */
	private function readConfiguration($file)
	{
		$definition = Neon::decode(file_get_contents($file));
		$dir = dirname($file);

		assert(isset($definition['namespace']));
		assert(isset($definition['output']));

		$this->constants = isset($definition['constants']) ? $definition['constants'] : [];
		$this->values = isset($definition['values']) ? $definition['values'] : [];

		return new Configuration($definition['output'], $definition['namespace'], $dir);
	}

}
