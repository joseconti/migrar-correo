<?php
/**
 * Script variables
 */


$source_connectionstring = '{dominio_IP_Servidor_antinuo:143/imap/notls}INBOX';
$source_username         = 'usuario';
$source_password         = 'contraseña';

$target_connectionstring = '{dominio_IP_Servidor_nuevoo:993/imap/ssl/novalidate-cert}INBOX';
$target_username         = 'usuario';
$target_password         = 'contraseña';


$migratedfolders = array();

/**
 * Write log to the filesystem
 *
 * @param string $message The message to write to the log.
 */
function errorlog( $message ) {
	$data = '[' . date( 'F j, Y, g:i a' ) . "]\t" . $message . "\n\n";
	file_put_contents( '/tmp/imap_migration_log', $data, FILE_APPEND );
}

/**
 * Keep track on processed folders
 *
 * @param string $folder The folder to keep track on.
 * */
function migratedfolders( $folder ) {
	file_put_contents( '/tmp/imap_migration_history', $folder . "\n", FILE_APPEND );
}


/*
Start the job
*/

/* Get the complete list of all source folders */
$source_imap = imap_open( $source_connectionstring, $source_username, $source_password )
or die( "can't connect: " . imap_last_error() );

$folders = imap_list( $source_imap, $source_connectionstring, '*' );

imap_close( $source_imap );

if ( $folders !== false ) {

	foreach ( $folders as $value ) {

		/* Remove connection info in the path */
		$folderpath = str_replace( $source_connectionstring, '', $value );

		/* Check if the folder already are completely migrated */
		if ( is_file( '/tmp/imap_migration_history' ) ) {
			$migratedfolders = explode( "\n", file_get_contents( '/tmp/imap_migration_history' ) );
		}

		if ( ! in_array( $folderpath, $migratedfolders ) ) {

			/* Output info */
			echo 'Working on ' . $folderpath . '/<br />';

			/* Remove the folder name and keep the path     */
			$targetpath = explode( '/', $folderpath );
			array_pop( $targetpath );
			$targetroot = mb_convert_encoding( implode( '/', $targetpath ), 'UTF7-IMAP', 'ISO-8859-1' );

			/* Connect to the server */
			$target_imap = imap_open( $target_connectionstring . $target_folder . $targetroot, $target_username, $target_password )
			or die( "can't connect: " . imap_last_error() );

			/* Disconnect */
			imap_close( $target_imap );

			/* Connect to the servers */
			$target_imap = imap_open( mb_convert_encoding( $target_connectionstring . $folderpath, 'UTF7-IMAP', 'ISO-8859-1' ), $target_username, $target_password )
			or die( "can't connect: " . imap_last_error() );

			$source_imap = imap_open( $source_connectionstring . $folderpath, $source_username, $source_password )
			or die( "can't connect: " . imap_last_error() );

			/* Get information about the source mailbox */
			$MC = imap_check( $source_imap );

			print "$MC->Nmsgs mails to migrate:";

			/* Fetch messages from source mailbox */
			$result = imap_fetch_overview( $source_imap, "1:{$MC->Nmsgs}", 0 );

			foreach ( $result as $overview ) {
				$message = imap_fetchheader( $source_imap, $overview->msgno ) . imap_body( $source_imap, $overview->msgno );

				/* Write message to target mailbox */
				if ( ! imap_append( $target_imap, mb_convert_encoding( $target_connectionstring . $folderpath, 'UTF7-IMAP', 'ISO-8859-1' ), $message, '\\Seen' ) ) {

					/* Log errors */
					errorlog( "Coundn't migrate the email \"$overview->subject\" from $overview->date in the $folderpath" );

					/* Output info */
					print '|';
				} else {
					/* Output info */
					print '.';
				}
			}

			/* Fetch messages from source mailbox */
			imap_close( $source_imap );
			imap_close( $target_imap );

			/* Write to the history that we are finished with the folder */
			migratedfolders( $folderpath );
		}
	}

	/* Delete history file */
	unlink( '/tmp/imap_migration_history' );

} else {
	echo 'Couldn\'t get source IMAP trees!';
}
