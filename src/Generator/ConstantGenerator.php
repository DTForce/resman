<?php
/**
 * This file is part of ResMan library.
 *
 * Copyright (c) 2015 DTForce, s.r.o. (http://www.dtforce.com)
 *
 * For the full copyright and license information, please view
 * the file LICENSE that was distributed with this source code.
 */

namespace DTForce\ResMan\Generator;

use DTForce\ResMan\Configuration;
use Nette\Neon\Neon;
use PhpParser;
use PhpParser\BuilderFactory;
use PhpParser\Node\Const_ as Const_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\PrettyPrinter;


final class ConstantGenerator
{

	/**
	 * @var Configuration
	 */
	private $configuration;


	public function __construct(Configuration $configuration)
	{
		$this->configuration = $configuration;
	}


	/**
	 * @param string $path
	 */
	public function generateFromNeonFile($path)
	{
		$definition = Neon::decode(file_get_contents($path));
		assert(isset($definition['class']));
		assert(isset($definition['type']));
		assert($definition['type'] === 'in-place');
		$data = $definition['data'];
		$output = $this->configuration->getDir() . DIRECTORY_SEPARATOR .
			$this->configuration->getOutputFolder() . DIRECTORY_SEPARATOR . $definition['class'] . '.php';

		$consts = Helper::createStringConstants($data);
		$node = $this->createClassFromData($definition['class'], $this->configuration->getNamespace(), $consts);

		$prettyPrinter = new PrettyPrinter\Standard();
		file_put_contents($output, $prettyPrinter->prettyPrintFile([$node]));
	}


	/**
	 * @param string $className
	 * @param string $namespace
	 * @param Const_[] $consts
	 *
	 * @return PhpParser\Node
	 */
	private function createClassFromData($className, $namespace, $consts)
	{
		$factory = new BuilderFactory();
		return $factory->namespace($namespace)
			->addStmt(
				$factory->class($className)
				->addStmt(new ClassConst($consts))
			)->getNode();
	}

}
