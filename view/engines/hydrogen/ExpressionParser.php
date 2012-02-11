<?php
/*
 * Copyright (c) 2009 - 2012, Frosted Design
 * All rights reserved.
 */

namespace hydrogen\view\engines\hydrogen;

use hydrogen\common\TypedValue;
use hydrogen\view\engines\hydrogen\Lexer;
use hydrogen\view\engines\hydrogen\nodes\VariableNode;
use hydrogen\view\engines\hydrogen\exceptions\NoSuchFilterException;
use hydrogen\view\engines\hydrogen\exceptions\TemplateSyntaxException;

/**
 * ExpressionParser provides the Hydrogen Templating Engine with a way to
 * support context variables (with filters) in expressions, along with a few
 * convenience operators and basic validity checking.  After being parsed, the
 * resulting expression should be in a format executable by PHP itself.
 *
 * See the Hydrogen Front-End Development documentation for more information
 * on how Hydrogen template expressions are formatted.
 *
 * @link http://www.webdevrefinery.com/forums/topic/6404-hydrogen-templates-for-front-end-developers
 * 		Frontend Development Documentation
 */
class ExpressionParser {

	const TOKEN_NONE = 0;
	const TOKEN_OP = 1;
	const TOKEN_COMP = 2;
	const TOKEN_JOIN = 3;
	const TOKEN_NUM = 4;
	const TOKEN_VAR = 5;
	const TOKEN_OPENGROUP = 6;
	const TOKEN_CLOSEGROUP = 7;
	const TOKEN_INVERT = 8;
	const TOKEN_STRING = 9;
	const TOKEN_CONCAT = 10;
	const TOKEN_FUNC = 11;
	const TOKEN_PHP = 12;

	protected static $operators = array('-', '+', '/', '*', '%');

	protected static $comparators = array('<', '>', '==', '!=', '<=', '>=');

	protected static $joiners = array('&&', '||');

	protected static $functions = array("in", "empty", "exists");

	protected static $varTranslations = array(
		"and" => array(self::TOKEN_JOIN, '&&'),
		"or" => array(self::TOKEN_JOIN, '||'),
		"not" => array(self::TOKEN_INVERT, "!")
	);

	protected static $comparatorTranslations = array(
		"=" => array(self::TOKEN_COMP, '=='),
		"!" => array(self::TOKEN_INVERT, '!')
	);

	/**
	 * This is a statically accessed class and should never be instantiated.
	 */
	protected function __construct() {}

	/**
	 * Transforms a Hydrogen template expression to a pure PHP expression that
	 * can be executed directly or inserted in a PHP file.
	 *
	 * @param string $expr The Hydrogen template expression string to parse
	 * @param \hydrogen\view\engines\hydrogen\PHPFile $phpFile The instance
	 * 		of PHPFile being used to render this template.  This function will
	 * 		not add any content to the page, but variable filters (if used) may
	 * 		use it to add functions and variable declarations.
	 * @param string $origin The name of the template that this expression was
	 * 		taken from.  If not specified, the default is 'expression'.
	 * @return string An expression in pure executable PHP.
	 * @throws TemplateSyntaxException if a syntax error is found in the
	 * 		provided expression.
	 */
	public static function exprToPHP($expr, $phpFile, $origin='expression') {
		$state = self::TOKEN_NONE;
		$token = '';
		$tokens = array();
		$len = strlen($expr);
		$poss = array();
		$lastToken = self::TOKEN_NONE;
		$varInQuotes = false;
		$varEscaping = false;
		$varInFilter = false;
		$numHasDot = false;
		$stringEscaping = false;
		$groupRatio = 0;
		for ($i = 0; $i <= $len; $i++) {
			if ($i === $len)
				$char = ' ';
			else
				$char = $expr[$i];
			switch ($state) {
				case self::TOKEN_NONE:
					// Determine what state we should be in
					$token = $char;
					// Test for digit or decimal
					if (ctype_digit($char) || $char === '.')
						$state = self::TOKEN_NUM;
					// Test for alphabetical char
					else if (ctype_alpha($char))
						$state = self::TOKEN_VAR;
					// Test for operator
					else if (count($poss = static::filterArrayStartsWith(
							$char, static::$operators)) > 0)
						$state = self::TOKEN_OP;
					// Test for comparison char
					else if (count($poss = static::filterArrayStartsWith(
							$char, static::$comparators)) > 0)
						$state = self::TOKEN_COMP;
					// Test for joining char
					else if (count($poss = static::filterArrayStartsWith(
							$char, static::$joiners)) > 0)
						$state = self::TOKEN_JOIN;
					// Test for open group
					else if ($char === '(')
						$state = self::TOKEN_OPENGROUP;
					// Test for close group
					else if ($char === ')')
						$state = self::TOKEN_CLOSEGROUP;
					// Test for string
					else if ($char === '"')
						$state = self::TOKEN_STRING;
					// Spaces are legal, but nothing else
					else if ($char !== ' ')
						throw new TemplateSyntaxException(
							"Illegal character '" . $char .
							"' (ASCII " . ord($char) . ") in expression: '" .
							$expr . "'");
					break;
				case self::TOKEN_STRING:
					// Current char can be anything but an unescaped quote.
					$token .= $char;
					if ($stringEscaping === false && $char === '"') {
						if ($lastToken === self::TOKEN_NONE ||
								$lastToken === self::TOKEN_COMP ||
								$lastToken === self::TOKEN_CONCAT ||
								$lastToken === self::TOKEN_FUNC ||
								$lastToken === self::TOKEN_JOIN ||
								$lastToken === self::TOKEN_OPENGROUP) {
							$tokens[] = new TypedValue(
								self::TOKEN_STRING, $token);
							$state = self::TOKEN_NONE;
							$lastToken = self::TOKEN_STRING;
						}
						else
							throw new TemplateSyntaxException(
								"Misplaced string $token in expression $expr");
					}
					else if ($stringEscaping === true)
						$stringEscaping = false;
					else if ($char === '\\')
						$stringEscaping = true;
					break;
				case self::TOKEN_NUM:
					// Current char must be numeric or decimal
					if ($token === '.')
						$numHasDot = true;
					if (ctype_digit($char))
						$token .= $char;
					else if ($char === '.') {
						if (!$numHasDot) {
							$token .= $char;
							$numHasDot = true;
						}
						else
							throw new TemplateSyntaxException(
								"Number '" . $token .
								"' cannot have multiple decimal points in expression: '" .
								$expr . "'");
					}
					else if ($token === '.') {
						// If our token is JUST a dot, this isn't a number
						// at all-- it's a concatenator
						$state = self::TOKEN_CONCAT;
						$numHasDot = false;
						$i--;
					}
					else if ($lastToken === self::TOKEN_NONE ||
							$lastToken === self::TOKEN_COMP ||
							$lastToken === self::TOKEN_JOIN ||
							$lastToken === self::TOKEN_OP ||
							$lastToken === self::TOKEN_OPENGROUP ||
							$lastToken === self::TOKEN_CONCAT) {
						$tokens[] = new TypedValue(self::TOKEN_NUM, $token);
						$lastToken = self::TOKEN_NUM;
						$state = self::TOKEN_NONE;
						$numHasDot = false;
						$i--;
					}
					else
						throw new TemplateSyntaxException(
							"Misplaced numeric '" . $token .
							"' in expression: '" . $expr . "'");
					break;
				case self::TOKEN_VAR:
					$endClean = false;
					// If we're in quotes, allow anything
					if ($varInQuotes) {
						if ($char === '"' && $varEscaping === false)
							$varInQuotes = false;
						else if ($char === '\\' || $varEscaping === true)
							$varEscaping = !$varEscaping;
						$token .= $char;
					}
					else if ($char === Lexer::VARIABLE_FILTER_SEPARATOR) {
						$token .= $char;
						$varInFilter = true;
					}
					else if ($varInFilter === true) {
						// If the last character was a filter separator,
						// allow only alphas.  Otherwise, all legals work.
						$lastChar = $token[strlen($token) - 1];
						if (($lastChar === Lexer::VARIABLE_FILTER_SEPARATOR &&
								ctype_alpha($char)) || ($lastChar !==
								Lexer::VARIABLE_FILTER_SEPARATOR &&
								(ctype_alnum($char) || $char === '_' ||
								$char ===
								Lexer::VARIABLE_FILTER_ARGUMENT_SEPARATOR)))
							$token .= $char;
						else if ($lastChar ===
								Lexer::VARIABLE_FILTER_SEPARATOR ||
								$lastChar ===
								Lexer::VARIABLE_FILTER_ARGUMENT_SEPARATOR)
							throw new TemplateSyntaxException(
							"Illegal character in filter: '" . $char .
							"' for expression: '" . $expr . "'.");
						else
							$endClean = true;
					}
					else {
						// We must be in the normal variable name.  Allow
						// alphanumerics, underscores, and variable level
						// separators.
						if (ctype_alnum($char) || $char === '_' ||
								$char === Lexer::VARIABLE_LEVEL_SEPARATOR)
							$token .= $char;
						else
							$endClean = true;
					}
					if ($endClean) {
						// Do we need to translate the result?
						if (isset(static::$varTranslations[$token])) {
							$state = static::$varTranslations[$token][0];
							$token = static::$varTranslations[$token][1];
							$i--;
						}
						// Are we looking at a function instead of a variable?
						if (in_array($token, static::$functions)) {
							$state = self::TOKEN_NONE;
							$lastToken = self::TOKEN_FUNC;
							$tokens[] = new TypedValue(self::TOKEN_FUNC,
								$token);
							$i--;
						}
						// It's a variable! Bag it and tag it.
						else if ($lastToken === self::TOKEN_NONE ||
								$lastToken === self::TOKEN_COMP ||
								$lastToken === self::TOKEN_INVERT ||
								$lastToken === self::TOKEN_JOIN ||
								$lastToken === self::TOKEN_OP ||
								$lastToken === self::TOKEN_OPENGROUP ||
								$lastToken === self::TOKEN_FUNC ||
								$lastToken === self::TOKEN_CONCAT) {
							$tokens[] = new TypedValue(self::TOKEN_VAR,
								$token);
							$state = self::TOKEN_NONE;
							$lastToken = self::TOKEN_VAR;
							$varInFilter = false;
							$varEscaping = false;
							$varInQuotes = false;
							$i--;
						}
						else
							throw new TemplateSyntaxException(
								"Misplaced variable '" . $token .
								"' in expression: '" . $expr . "'");
					}
					break;
				case self::TOKEN_OP:
				case self::TOKEN_COMP:
				case self::TOKEN_JOIN:
					$poss = static::filterArrayStartsWith($token . $char,
						$poss);
					if (count($poss) > 0)
						$token .= $char;
					// Check to see if this should be translated
					else if ($state === self::TOKEN_COMP &&
							isset(static::$comparatorTranslations[$token])) {
						$state = static::$comparatorTranslations[$token][0];
						$token = static::$comparatorTranslations[$token][1];
						$i--;
					}
					// If the token's a minus, see if this should be a number
					else if ($state === self::TOKEN_OP &&
							$token === '-' && ctype_digit($char) &&
							($lastToken === self::TOKEN_NONE ||
							$lastToken === self::TOKEN_COMP ||
							$lastToken === self::TOKEN_JOIN ||
							$lastToken === self::TOKEN_OP ||
							$lastToken === self::TOKEN_OPENGROUP)) {
						$state = self::TOKEN_NUM;
						$i--;
					}
					// We're all set!  It's whatever the state is.
					else if ($lastToken === self::TOKEN_VAR ||
							$lastToken === self::TOKEN_NUM ||
							$lastToken === self::TOKEN_CLOSEGROUP) {
						$tokens[] = new TypedValue($state, $token);
						$lastToken = $state;
						$state = self::TOKEN_NONE;
						$i--;
					}
					else
						throw new TemplateSyntaxException(
							"Misplaced '" . $token .
							"' in expression: '" . $expr . "'");
					break;
				case self::TOKEN_OPENGROUP:
					if ($lastToken !== self::TOKEN_VAR &&
							$lastToken !== self::TOKEN_NUM) {
						$tokens[] = new TypedValue(self::TOKEN_OPENGROUP,
							$token);
						$groupRatio++;
						$state = self::TOKEN_NONE;
						$lastToken = self::TOKEN_OPENGROUP;
						$i--;
					}
					else
						throw new TemplateSyntaxException(
							"Misplaced '(' in expression: '" .
							$expr . "'.");
					break;
				case self::TOKEN_CLOSEGROUP:
					if (($lastToken === self::TOKEN_VAR ||
							$lastToken === self::TOKEN_NUM ||
							$lastToken === self::TOKEN_STRING ||
							$lastToken === self::TOKEN_CLOSEGROUP) &&
							$groupRatio > 0) {
						$tokens[] = new TypedValue(self::TOKEN_CLOSEGROUP,
							$token);
						$groupRatio--;
						$state = self::TOKEN_NONE;
						$lastToken = self::TOKEN_CLOSEGROUP;
						$i--;
					}
					else
						throw new TemplateSyntaxException(
							"Misplaced ')' in expression: '" .
							$expr . "'.");
					break;
				case self::TOKEN_INVERT:
					if ($lastToken === self::TOKEN_NONE ||
							$lastToken === self::TOKEN_COMP ||
							$lastToken === self::TOKEN_JOIN ||
							$lastToken === self::TOKEN_OPENGROUP) {
						$tokens[] = new TypedValue(self::TOKEN_INVERT, $token);
						$state = self::TOKEN_NONE;
						$lastToken = self::TOKEN_INVERT;
						$i--;
					}
					else
						throw new TemplateSyntaxException(
							"Misplaced '!' in expression: '" .
							$expr . "'.");
					break;
				case self::TOKEN_CONCAT:
					if ($lastToken === self::TOKEN_CLOSEGROUP ||
							$lastToken === self::TOKEN_NUM ||
							$lastToken === self::TOKEN_STRING ||
							$lastToken === self::TOKEN_VAR) {
						$tokens[] = new TypedValue(self::TOKEN_CONCAT, $token);
						$state = self::TOKEN_NONE;
						$lastToken = self::TOKEN_CONCAT;
						$i--;
					}
					else
						throw new TemplateSyntaxException(
							"Misplaced '.' in expression: '" . $expr . "'.");
			}
		}
		if ($groupRatio !== 0)
			throw new TemplateSyntaxException("Missing ')' in expression: '" .
				$expr . "'.");

		// Expand the function keywords
		$len = count($tokens);
		for ($i = 0; $i < $len; $i++) {
			// Support the 'exists' function
			if ($tokens[$i]->type === self::TOKEN_FUNC &&
					$tokens[$i]->value === 'exists') {
				if (isset($tokens[$i + 1]) &&
						$tokens[$i + 1]->type === self::TOKEN_VAR) {
					$var = $tokens[$i + 1];
					$var->type = self::TOKEN_PHP;
					$var->value = static::parseVariableString($var->value,
						$phpFile, $origin, true);
					$inst = array(
						new TypedValue(self::TOKEN_PHP, '!is_null('),
						$var,
						new TypedValue(self::TOKEN_PHP, ')')
					);
					array_splice($tokens, $i, 2, $inst);
					$added = count($inst) - 2;
					$len += $added;
					$i += $added;
				}
				else
					throw new TemplateSyntaxException("Keyword 'exists' must be used before a variable in expression: $expr");
			}
			// Support the 'empty' function
			if ($tokens[$i]->type === self::TOKEN_FUNC &&
					$tokens[$i]->value === 'empty') {
				if (isset($tokens[$i + 1]) &&
						$tokens[$i + 1]->type === self::TOKEN_VAR) {
					$var = $tokens[$i + 1];
					$var->type = self::TOKEN_PHP;
					$var->value = static::parseVariableString($var->value,
						$phpFile, $origin, true);
					$inst = array(
						new TypedValue(self::TOKEN_PHP, '(is_null('),
						$var,
						new TypedValue(self::TOKEN_PHP, ') || '),
						$var,
						new TypedValue(self::TOKEN_PHP,
							' === "" || (is_array('),
						$var,
						new TypedValue(self::TOKEN_PHP, ') && count('),
						$var,
						new TypedValue(self::TOKEN_PHP, ') === 0))')
					);
					array_splice($tokens, $i, 2, $inst);
					$added = count($inst) - 2;
					$len += $added;
					$i += $added;
				}
				else
					throw new TemplateSyntaxException("Keyword 'empty' must be used before a variable in expression: $expr");
			}
			// Support the 'in' function
			if ($tokens[$i]->type === self::TOKEN_FUNC &&
					$tokens[$i]->value === 'in') {
				if ($i !== 0 && isset($tokens[$i + 1]) &&
						($tokens[$i - 1]->type === self::TOKEN_VAR ||
						$tokens[$i - 1]->type === self::TOKEN_STRING) &&
						($tokens[$i + 1]->type === self::TOKEN_VAR ||
						$tokens[$i + 1]->type === self::TOKEN_STRING)) {
					$var1 = $tokens[$i - 1];
					$var2 = $tokens[$i + 1];
					$inst = array(
						new TypedValue(self::TOKEN_PHP, '((!is_array('),
						$var2,
						new TypedValue(self::TOKEN_PHP, ') && strpos('),
						clone $var2,
						new TypedValue(self::TOKEN_PHP, ', '),
						$var1,
						new TypedValue(self::TOKEN_PHP,
							') !== false) || (is_array('),
						clone $var2,
						new TypedValue(self::TOKEN_PHP, ') && in_array('),
						clone $var1,
						new TypedValue(self::TOKEN_PHP, ', '),
						clone $var2,
						new TypedValue(self::TOKEN_PHP, ')))')
					);
					array_splice($tokens, $i - 1, 3, $inst);
					$added = count($inst) - 3;
					$len += $added;
					$i += $added;
				}
				else
					throw new TemplateSyntaxException("Keyword 'in' must be used between two arrays or strings in expression: $expr");
			}
		}

		// Rewrite the variables to be pulled from the context
		$len = count($tokens);
		for ($i = 0; $i < $len; $i++) {
			if ($tokens[$i]->type === self::TOKEN_VAR) {
				$tokens[$i]->value = static::parseVariableString(
					$tokens[$i]->value, $phpFile, $origin);
			}
		}
		
		return implode(' ', $tokens);
	}

	/**
	 * Takes a source array of strings and returns a new array made up only of
	 * elements that start with a specified string.  The elements will remain
	 * in the same order in the resulting array, just with elements removed.
	 *
	 * @param string $needle The string with which to filter the source array.
	 * 		Only elements that start with this string will be put in the
	 * 		resulting array.
	 * @param array $haystack The source array to be filtered.
	 * @return array A new array with a subset of the items in the source
	 * 		array, each starting with the string specified in $needle.
	 */
	protected static function filterArrayStartsWith($needle, $haystack) {
		$num = count($haystack);
		$len = strlen($needle);
		for ($i = $num - 1; $i >= 0; $i--) {
			if ($len > strlen($haystack[$i]))
				array_splice($haystack, $i, 1);
			else {
				for ($q = 0; $q < $len; $q++) {
					if ($needle[$q] !== $haystack[$i][$q]) {
						array_splice($haystack, $i, 1);
						break;
					}
				}
			}
		}
		return $haystack;
	}

	/**
	 * Turns a variable string (similar to what might be found in a variable
	 * tag within a template) into pure PHP.
	 *
	 * @param string $varString The variable string to be parsed into PHP.
	 * @param \hydrogen\view\engines\hydrogen\PHPFile $phpFile The instance
	 * 		of PHPFile being used to render this template.
	 * @param string $origin The name of the template from which this variable
	 * 		string was taken.
	 * @param boolean $nullIfNotFound true if a variable should evaluate to
	 * 		NULL if it's not found, false if it should throw an exception in
	 * 		that case.
	 * @return string the pure PHP code that will evaluate the provided
	 * 		variable string.
	 */
	protected static function parseVariableString($varString,
			$phpFile, $origin, $nullIfNotFound=false) {
		$token = Lexer::getVariableToken($origin, $varString);
		$vNode = new VariableNode($token->varLevels, $token->filters,
			false, $origin);
		return $vNode->getVariablePHP($phpFile, false, $nullIfNotFound);
	}
}

?>