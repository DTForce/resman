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

use DirectoryIterator;
use DTForce\ResMan\Configuration;
use DTForce\ResMan\Exception\MissingKeyInVersionException;
use DTForce\ResMan\Exception\UndefinedKeysFoundException;
use Nette\Neon\Neon;
use PhpParser;
use PhpParser\Builder\Method;
use PhpParser\Builder\Property;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
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

		$versionKeyPrefix = isset($definition['versionKeyPrefix']) ? $definition['versionKeyPrefix'] : 'VERSION_';

		$versions = $this->loadVersions($definition, $actualDir);
		$keysToValuesKeys = $this->createKeysFromVersions($definition, $versions);
		$versions = $this->processVersions($versions, $keysToValuesKeys);

		$constants = $this->addVersionKeysConstants($versions, $versionKeyPrefix);
		$node = $this->createValueClassFromVersions($definition, $constants, $versions);

		$addFolder = null;
		if (isset($definition['addNamespace'])) {
			$addFolder = implode(DIRECTORY_SEPARATOR, explode('\\', $definition['addNamespace']));
		}

		$this->saveGeneratedFile($this->getOutputPath($this->getClassName($definition), $addFolder), $node);
	}


	/**
	 * @param array $definition
	 * @param ClassConst[] $constants
	 * @param $versions
	 *
	 * @return PhpParser\Node
	 *
	 */
	private function createValueClassFromVersions(array $definition, $constants, $versions)
	{
		$className = $this->getClassName($definition);
		$factory = new BuilderFactory();
		return $factory->namespace($this->getNamespace($definition))
			->addStmt(
				$factory->class($className)
					->addStmts($constants)
					->addStmt(
						$this->createValueField($factory, Helper::createArray($versions))
					)
					->addStmt(
						$this->createAllowedVersionsField($factory, Helper::createArray(array_keys($versions)))
					)
					->addStmt(
						$this->createGetValueMethod($factory, $className)
					)
					->addStmt(
						$this->createHasValueMethod($factory, $className)
					)
					->addStmt(
						$this->createGetDefaultVersionMethod($factory, $this->getDefaultVersion($definition))
					)
					->addStmt(
						$this->createIsVersionAllowedMethod($factory, $className)
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
	 * @param array $result
	 * @param array $keysToValuesKeys
	 *
	 * @return array
	 * @throws MissingKeyInVersionException
	 * @throws UndefinedKeysFoundException
	 */
	private function processVersions(array $result, array $keysToValuesKeys)
	{

		foreach ($result as $name => $version) {
			$result[$name] = $this->processVersion($name, $version, $keysToValuesKeys);
		}

		return $result;
	}


	/**
	 * @param string $name
	 * @param array $version
	 * @param array $keysToValuesKeys
	 *
	 * @return array
	 * @throws MissingKeyInVersionException
	 * @throws UndefinedKeysFoundException
	 */
	private function processVersion($name, array $version, array $keysToValuesKeys)
	{
		$newVersion = [];
		foreach ($keysToValuesKeys as $partKey => $part) {
			if (!isset($version[$partKey])) {
				throw new MissingKeyInVersionException($name, $partKey);
			}
			foreach ($part as $key => $newKey) {
				$newVersion[$newKey] = $version[$partKey][$key];
				unset($version[$partKey][$key]);
			}
			if (count($version[$partKey])) {
				throw new UndefinedKeysFoundException(array_keys($version), $name);
			}
			unset($version[$partKey]);
		}

		if (count($version)) {
			throw new UndefinedKeysFoundException(array_keys($version), $name);
		}
		return $newVersion;
	}


	/**
	 * @param $versions
	 * @param $versionKeyPrefix
	 *
	 * @return ClassConst[]
	 */
	private function addVersionKeysConstants($versions, $versionKeyPrefix)
	{
		$constants = [];
		foreach ($versions as $versionName => $version) {
			$constants[$versionKeyPrefix . strtoupper($versionName)] = $versionName;
		}
		return Helper::createStringConstants($constants);
	}


	/**
	 * @param $output
	 * @param $node
	 */
	private function saveGeneratedFile($output, $node)
	{
		$prettyPrinter = new PrettyPrinter\Standard();
		@mkdir(dirname($output), 0777, true);
		file_put_contents($output, $prettyPrinter->prettyPrintFile([$node]));
	}


	/**
	 *
	 * @param $definition
	 * @param $actualDir
	 *
	 * @return array
	 */
	private function loadVersions($definition, $actualDir)
	{
		$versions = $definition['versions'];
		$type = $this->getType($definition);
		if ($type === 'csv') {
			$result = $this->loadCsvVersions($versions, $actualDir);
			return $result;
		} else {
			$result = $versions;
			return $result;
		}
	}


	/**
	 * @param array $definition
	 * @param $result
	 *
	 * @return array
	 */
	private function createKeysFromVersions(array $definition, $result)
	{
		$defaultVersion = $this->getDefaultVersion($definition);

		$keysToValuesKeys = [];
		foreach ($result[$defaultVersion] as $key => $value) {
			foreach ($value as $key2 => $value2) {
				$linearKey = strtoupper($key) . '_' . $key2;
				$keysToValuesKeys[$key][$key2] = $linearKey;
			}
			$this->createKeyConstants($definition, Helper::toPascalCase($key), $keysToValuesKeys[$key]);
		}

		return $keysToValuesKeys;
	}


	/**
	 *
	 * @param $className
	 * @param null $addFolder
	 *
	 * @return string
	 */
	private function getOutputPath($className, $addFolder = null)
	{
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
	 * @param $definition
	 *
	 * @return array
	 */
	private function getDefaultVersion($definition)
	{
		assert(isset($definition['defaultVersion']));
		$defaultVersion = $definition['defaultVersion'];
		return $defaultVersion;
	}


	/**
	 * @param $definition
	 *
	 * @return mixed
	 */
	private function getClassName($definition)
	{
		assert(isset($definition['class']));
		return $definition['class'];
	}


	/**
	 * @param array $definition
	 * @param string $className
	 * @param array $keys
	 *
	 * @return array|PhpParser\Node\Const_[]
	 */
	private function createKeyConstants(array $definition, $className, array $keys)
	{

		$constants = Helper::createStringConstants($keys);
		$factory = new BuilderFactory();
		$classNode = $factory->namespace($this->getNamespace($definition, 'Keys'))
							->addStmt($factory->class($className)
								->addStmts($constants)
							)->getNode();

		$addFolder = null;
		if (isset($definition['addNamespace'])) {
			$addFolder = implode(DIRECTORY_SEPARATOR, explode('\\', $definition['addNamespace'] . '\\' . 'Keys'));
		} else {
			$addFolder = 'Keys';
		}
		$this->saveGeneratedFile($this->getOutputPath($className, $addFolder), $classNode);
		return $constants;
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
	 * @param $versions
	 * @param $actualDir
	 *
	 * @return array
	 */
	private function loadCsvVersions($versions, $actualDir)
	{
		$result = [];
		foreach ($versions as $name => $version) {
			$dir = new DirectoryIterator($actualDir . DIRECTORY_SEPARATOR . $version);
			foreach ($dir as $file) {
				if ( ! $file->isDir()) {
					$tableName = $file->getBasename(".csv");
					$result[$name][$tableName] = Helper::readCsvValues($file->getRealPath());
				}
			}
		}
		return $result;
	}


	/**
	 * @param $factory
	 * @param $className
	 *
	 * @return Method
	 */
	private function createIsVersionAllowedMethod(BuilderFactory $factory, $className)
	{
		return $factory->method('isVersionAllowed')
			->makePublic()
			->makeStatic()
			->addParam(
				$factory->param('version')
			)
			->addStmts(
				[
					new Return_(
						new FuncCall(
							new Name("in_array"), [new Arg(new Variable("version")), new Arg(
							new StaticPropertyFetch(new Name($className), 'allowedVersions')
						)]
						)
					)
				]
			);
	}


	/**
	 * @param $factory
	 * @param $defaultVersion
	 *
	 * @return Method
	 */
	private function createGetDefaultVersionMethod(BuilderFactory $factory, $defaultVersion)
	{
		return $factory->method('getDefaultVersion')
			->makePublic()
			->makeStatic()
			->addStmts(
				[
					new Return_(new String_($defaultVersion))
				]
			);
	}


	/**
	 * @param $factory
	 * @param $className
	 *
	 * @return Method
	 */
	private function createHasValueMethod(BuilderFactory $factory, $className)
	{
		return $factory->method('hasValue')
			->makePublic()
			->makeStatic()
			->addParam(
				$factory->param('key')
			)
			->addParam(
				$factory->param('version')
			)
			->addStmts(
				[
					new Return_(
						new PhpParser\Node\Expr\Isset_(
							[
								new ArrayDimFetch(
									new ArrayDimFetch(
										new StaticPropertyFetch(new Name($className), 'values'),
										new Variable('version')
									),
									new Variable('key')
								)
							]
						)
					)
				]
			);
	}


	/**
	 * @param $className
	 * @param $factory
	 *
	 * @return Method
	 */
	private function createGetValueMethod(BuilderFactory $factory, $className)
	{
		return $factory->method('getValue')
			->makePublic()
			->makeStatic()
			->addParam(
				$factory->param('key')
			)
			->addParam(
				$factory->param('version')
			)
			->addStmts(
				[
					new Return_(
						new ArrayDimFetch(
							new ArrayDimFetch(
								new StaticPropertyFetch(new Name($className), 'values'),
								new Variable('version')
							),
							new Variable('key')
						)
					)
				]
			);
	}


	/**
	 * @param $factory
	 * @param Array_ $allowedVersions
	 *
	 * @return Property
	 */
	private function createAllowedVersionsField(BuilderFactory $factory, Array_ $allowedVersions)
	{
		return $factory->property('allowedVersions')
			->makePrivate()
			->makeStatic()
			->setDefault($allowedVersions);
	}


	/**
	 * @param Array_ $values
	 * @param BuilderFactory $factory
	 *
	 * @return Property
	 */
	private function createValueField(BuilderFactory $factory, Array_ $values)
	{
		return $factory->property('values')
			->makePrivate()
			->makeStatic()
			->setDefault($values);
	}

}
