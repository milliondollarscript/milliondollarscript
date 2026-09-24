<?php

declare(strict_types=1);

// Exercises the nikic/php-parser consumers: the AST walk that feeds the POT
// generator (LanguageFunctionVisitor) plus a full parse of the plugin source.

$pluginRoot = dirname(__DIR__, 2);
$failures = [];

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $pluginRoot . '/' );
}

require $pluginRoot . '/vendor/autoload.php';

use MillionDollarScript\Classes\Language\LanguageFunctionVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

function assert_true( bool $condition, string $label ): void {
	global $failures;

	if ( ! $condition ) {
		$failures[] = $label;
	}
}

$parser = ( new ParserFactory() )->createForHostVersion();

$fixture = <<<'PHP'
<?php
class Sample {
	public function a() {
		return Language::get( 'Extracted get' );
	}
	public function b() {
		return Language::get_replace( 'Extracted %s', [ '%s' ], [ 'value' ] );
	}
	public function c() {
		return Language::out( 'Extracted out' );
	}
	public function d() {
		return Language::notALanguageFunction( 'Ignored' );
	}
}
PHP;

$traverser = new NodeTraverser();
$visitor   = new LanguageFunctionVisitor();
$traverser->addVisitor( $visitor );
$traverser->traverse( $parser->parse( $fixture ) );

$found = array_map(
	static fn( array $entry ): string => $entry['function'] . ':' . ( $entry['string']->value ?? '' ),
	$visitor->strings
);

assert_true(
	$found === [ 'get:Extracted get', 'get_replace:Extracted %s', 'out:Extracted out' ],
	'language scanner must collect Language::get/get_replace/out strings and ignore other calls, got: ' . implode( ', ', $found )
);

$parseErrors = [];
$files       = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $pluginRoot . '/src' ) );
$parsed      = 0;

foreach ( $files as $file ) {
	if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
		continue;
	}

	try {
		$parser->parse( (string) file_get_contents( $file->getPathname() ) );
		++$parsed;
	} catch ( Throwable $error ) {
		$parseErrors[] = $file->getPathname() . ': ' . $error->getMessage();
	}
}

assert_true( $parsed > 150, 'php-parser must parse the plugin source, parsed only ' . $parsed . ' file(s)' );
assert_true(
	$parseErrors === [],
	'php-parser must parse every plugin source file without errors: ' . implode( '; ', array_slice( $parseErrors, 0, 5 ) )
);

if ( $failures !== [] ) {
	foreach ( $failures as $failure ) {
		echo "FAIL: {$failure}\n";
	}

	exit( 1 );
}

echo "All language scanner parser tests passed ({$parsed} source files parsed).\n";