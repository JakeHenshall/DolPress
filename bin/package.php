<?php
/**
 * Build a production ZIP that installs without Composer or Node.
 *
 * @package DolPress
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$dist = $root . '/dist';
$zip  = $dist . '/dolpress-0.1.0.zip';

if ( ! is_dir( $dist ) ) {
	mkdir( $dist, 0755, true );
}

$exclude = array(
	'.git',
	'.github',
	'node_modules',
	'vendor',
	'TempleOS-archive',
	'tests',
	'src',
	'dist',
	'.phpunit.cache',
	'composer.json',
	'composer.lock',
	'package.json',
	'package-lock.json',
	'phpcs.xml.dist',
	'phpstan.neon',
	'phpunit.xml.dist',
	'tsconfig.json',
);

$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $current ) use ( $root, $exclude ): bool {
			$relative = substr( $current->getPathname(), strlen( $root ) + 1 );
			$top      = explode( DIRECTORY_SEPARATOR, $relative )[0];
			return ! in_array( $top, $exclude, true ) && ! str_starts_with( $current->getFilename(), '.' );
		}
	)
);

$archive = new ZipArchive();
if ( true !== $archive->open( $zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create ZIP\n" );
	exit( 1 );
}

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}
	$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
	$archive->addFile( $file->getPathname(), 'dolpress/' . str_replace( '\\', '/', $relative ) );
}

$archive->close();
echo $zip, PHP_EOL;
