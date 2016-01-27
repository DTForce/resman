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
use DTForce\ResMan\Exception\MissingKeyInVersionException;
use DTForce\ResMan\Exception\UndefinedKeysFoundException;
use Nette\Neon\Neon;
use PhpParser;
use PhpParser\BuilderFactory;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter;


final class ValuesGenerator
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
		$actualDir = dirname($path);
		$definition = Neon::decode(file_get_contents($path));

		assert(isset($definition['class']));
		assert(isset($definition['defaultVersion']));

		$type = $this->getType($definition);
		$defaultVersion = $definition['defaultVersion'];
		list($versions, $keys) = $this->processVersions($definition['versions'], $type, $actualDir, $defaultVersion);

		$output = $this->configuration->getDir() . DIRECTORY_SEPARATOR .
			$this->configuration->getOutputFolder() . DIRECTORY_SEPARATOR . $definition['class'] . '.php';

		$constants = Helper::createIntConstants(array_flip($keys));
		$node = $this->createClassFromData(
			$definition['class'],
			$this->configuration->getNamespace(),
			$constants,
			$defaultVersion,
			Helper::createArray($versions)
		);

		$prettyPrinter = new PrettyPrinter\Standard();
		file_put_contents($output, $prettyPrinter->prettyPrintFile([$node]));
	}


	/**
	 * @param string $className
	 * @param string $namespace
	 * @param Const_[] $consts
	 * @param Array_ $values
	 *
	 * @return PhpParser\Node
	 */
	private function createClassFromData($className, $namespace, $consts, $defaultVersion, Array_ $values)
	{
		$factory = new BuilderFactory();
		return $factory->namespace($namespace)
			->addStmt(
				$factory->class($className)
					->addStmt(new ClassConst($consts))
					->addStmt(
						$factory->property('values')
							->makePrivate()
							->makeStatic()
							->setDefault($values)
					)
					->addStmt(
						$factory->property('actualVersion')
							->makePrivate()
							->makeStatic()
							->setDefault($defaultVersion)
					)
					->addStmt(
						$factory->method('getValue')
							->makePublic()
							->makeStatic()
							->addParam(
								$factory->param('key')
							)
							->addParam(
								$factory->param('version')
									->setDefault(null)
							)
							->addStmts([
								new If_(new Identical(new Variable('version'), new PhpParser\Node\Expr\ConstFetch(new Name(["null"]))), [
									"stmts" => [new Assign(new Variable('version'), new StaticPropertyFetch(new Name($className), 'actualVersion'))]
								]),
								new Return_(new ArrayDimFetch(new ArrayDimFetch(new StaticPropertyFetch(new Name($className), 'values'), new Variable('version')), new Variable('key')))
							])
					)
			)->getNode();
	}


	/**
	 * @param $definition
	 *
	 * @return mixed
	 */
	private function getType($definition)
	{
		assert(isset($definition['type']));
		assert(in_array($definition['type'], ['in-place', 'csv']));
		return $definition['type'];
	}


	/**
	 * @param array $versions
	 * @param string $type
	 * @param string $actualDir
	 * @param string $defaultVersion
	 *
	 * @return array
	 */
	private function processVersions($versions, $type, $actualDir, $defaultVersion)
	{
		if ($type === 'csv') {
			$result = [];
			foreach ($versions as $name => $version) {
				$result[$name] = Helper::readCsvValues($actualDir . DIRECTORY_SEPARATOR . $version);
			}
		} else {
			$result = $versions;
		}
		$keys = array_keys($result[$defaultVersion]);
		$flippedKeys = array_flip($keys);

		foreach ($result as $name => $version) {
			$result[$name] = $this->processVersion($name, $version, $flippedKeys);
		}

		return [$result, $keys];
	}


	/**
	 * @param string $name
	 * @param string $version
	 * @param array $flippedKeys
	 *
	 * @return array
	 */
	private function processVersion($name, $version, array $flippedKeys)
	{
		$newVersion = [];
		foreach ($flippedKeys as $key => $newKey) {
			if (!isset($version[$key])) {
				throw new MissingKeyInVersionException($name, $key);
			}
			$newVersion[$newKey] = $version[$key];
			unset($version[$key]);
		}

		if (count($version)) {
			throw new UndefinedKeysFoundException(array_keys($version), $name);
		}
		return $newVersion;
	}

}
