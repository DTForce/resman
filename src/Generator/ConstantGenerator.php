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
use InvalidArgumentException;
use Nette\Neon\Neon;
use PhpParser;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Const_ as Const_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\PrettyPrinter;


final class ConstantGenerator
{

	const TYPE_IN_PLACE = 'in-place';
	const TYPE_CSV = 'csv';
	const TYPE_CSV_NAMED = 'csv-named';

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
		$actualDir = dirname($path);
		$definition = Neon::decode(file_get_contents($path));

		assert(isset($definition['class']));
		assert(isset($definition['type']));
		assert(in_array($definition['type'], [self::TYPE_IN_PLACE, self::TYPE_CSV, self::TYPE_CSV_NAMED]));

		switch ($definition['type']) {
			case self::TYPE_IN_PLACE:
				$consts = Helper::createStringConstants($definition['data']);
				break;
			case self::TYPE_CSV:
				$constsData = Helper::namesFromValues(
					Helper::readCsvValues($actualDir . DIRECTORY_SEPARATOR . $definition['data'])
				);
				$consts = Helper::createStringConstants($constsData);
				break;
			case self::TYPE_CSV_NAMED:
				$constsData = Helper::formatNames(
					Helper::readCsvKeysValues($actualDir . DIRECTORY_SEPARATOR . $definition['data'])
				);
				$consts = Helper::createStringConstants($constsData);
				break;
			default:
				throw new InvalidArgumentException("Bad data type.");
		}


		$node = $this->createClassFromData($definition['class'], $this->getNamespace($definition), $consts);
		$this->saveGeneratedFile($this->getOutputPath($definition), $node);
	}


	/**
	 * @param array $definition
	 * @param string|null $addFolder
	 *
	 * @return string
	 */
	private function getOutputPath(array $definition, $addFolder = null)
	{
		$className = $definition['class'];
		if (isset($definition['addNamespace'])) {
			$addFolder = $addFolder === null ?
				$definition['addNamespace'] : $definition['addNamespace'] . '\\' . $addFolder;
		}

		if ($addFolder !== null) {
			$output = implode(
				DIRECTORY_SEPARATOR, [
					$this->configuration->getDir(), $this->configuration->getOutputFolder(), $addFolder,
					$className . '.php']
			);
			return $output;
		} else {
			$output = implode(
				DIRECTORY_SEPARATOR, [
					$this->configuration->getDir(), $this->configuration->getOutputFolder(), $className . '.php'
				]
			);
			return $output;
		}
	}


	/**
	 * @param string $output
	 * @param $node
	 */
	private function saveGeneratedFile($output, Node $node)
	{
		$prettyPrinter = new PrettyPrinter\Standard();
		@mkdir(dirname($output), 0777, true);
		file_put_contents($output, $prettyPrinter->prettyPrintFile([$node]));
	}


	/**
	 * @param array $definition
	 *
	 * @param string|null $addNamespace
	 *
	 * @return array
	 */
	private function getNamespace(array $definition, $addNamespace = null)
	{
		if (isset($definition['addNamespace'])) {
			$addNamespace = $addNamespace === null ?
				$definition['addNamespace'] : $definition['addNamespace'] . '\\' . $addNamespace;
		}
		$namespace = $addNamespace === null ?
			$this->configuration->getNamespace() : $this->configuration->getNamespace() . '\\' . $addNamespace;
		return $namespace;
	}


	/**
	 * @param string $className
	 * @param string $namespace
	 * @param ClassConst[] $constants
	 *
	 * @return PhpParser\Node
	 */
	private function createClassFromData($className, $namespace, $constants)
	{
		$factory = new BuilderFactory();
		return $factory->namespace($namespace)
			->addStmt(
				$factory->class($className)
					->addStmts($constants)
			)->getNode();
	}

}
