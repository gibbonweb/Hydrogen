<?php
/*
 * Copyright (c) 2009 - 2011, Frosted Design
 * All rights reserved.
 */

namespace hydrogen\view\engines\hydrogen\nodes;

use hydrogen\view\engines\hydrogen\HydrogenEngine;
use hydrogen\view\engines\hydrogen\ExpressionParser;
use hydrogen\view\engines\hydrogen\Node;
use hydrogen\view\engines\hydrogen\exceptions\NoSuchFilterException;
use hydrogen\view\engines\hydrogen\exceptions\NoSuchVariableException;
use hydrogen\view\engines\hydrogen\Lexer;
use hydrogen\view\engines\hydrogen\PHPFile;
use hydrogen\config\Config;

class VariableNode implements Node {
	protected $varLevels;
	protected $filters;
	protected $origin;
	protected $escape;

	public function __construct($varLevels, $filters, $escape, $origin) {
		$this->varLevels = $varLevels;
		$this->filters = $filters ?: array();
		$this->escape = $escape === NULL || $escape ? true : false;
		$this->origin = $origin;
	}

	public function render($phpFile) {
		$phpFile->addPageContent(PHPFile::PHP_OPENTAG);
		$printVar = Config::getVal('view', 'print_missing_var') ?: false;
		if ($printVar)
			$phpFile->addPageContent('try {');
		$phpFile->addPageContent('echo ' .
			$this->getVariablePHP($phpFile) . ';');
		if ($printVar)
			$phpFile->addPageContent('} catch (\hydrogen\view\exceptions\NoSuchVariableException $e) { echo "{?", $e->variable, "?}"; }');
		$phpFile->addPageContent(PHPFile::PHP_CLOSETAG);
	}
	
	public function getVariablePHP($phpFile, $forSetting=false,
			$nullIfNotFound=false) {
		$var = '$context';
		if ($nullIfNotFound) {
			$var .= '->getWrapped(\'' . array_shift($this->varLevels) .
				'\', true)';
		}
		foreach ($this->varLevels as $level)
			$var .= "->" . $level;
		if (!$forSetting)
			$var .= "->getValue()";
		$escape = $this->escape;
		foreach ($this->filters as $filter) {
			$class = HydrogenEngine::getFilterClass($filter->filter);
			$var = $class::applyTo($var, $filter->args, $escape, $phpFile);
		}
		if ($escape)
			$var = 'htmlentities(' . $var . ')';
		return $var;
	}
}

?>