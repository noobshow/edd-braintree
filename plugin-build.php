<?php
$dir = @$argv[1] ?: dirname( __FILE__ );

if ( file_exists( $dir ) ) {
	deleteDir( $dir );
}

echo "changed directory to $dir" . "\n";
exec( "git clone https://github.com/OmnipayWP/edd-braintree.git $dir" );
echo "git clone completed." . "\n";

// if true, change directory otherwise exit.
chdir( $dir ) ?: exit;

function deleteDir( $path ) {
	if ( PHP_OS === 'Windows' ) {
		exec( "rd /s /q {$path}" );
	} else {
		exec( "rm -rf {$path}" );
	}
}

deleteDir( '.git' );
deleteDir( 'tests' );

echo ".git and tests folders deleted" . "\n";

exec( 'composer install --no-dev -o' );
echo "composer install completed." . "\n";

foreach (
	array(
		'.gitignore',
		'composer.json',
		'README.md',
		'plugin-build.php',
		'composer.lock',
		'edd-braintree.zip',
	) as $file
) {
	@unlink( $file );
}

// move up directory
chdir( str_replace( DIRECTORY_SEPARATOR . basename( $dir ), '', $dir ) );
echo "Archiving file" . "\n";
exec( "7z a edd-braintree.zip edd-braintree/" );
echo "Zip archiving completed" . "\n";

