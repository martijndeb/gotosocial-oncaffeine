<?php
if ( count( $argv ) < 2 || substr( $argv[ 1 ], -4 ) !== ".ini" ) {
	echo "Usage: php generate-directory.php <ini file>" . PHP_EOL;
	exit;
}

$fp = realpath( $argv[ 1 ] );
if ( file_exists( $fp ) && is_readable( $fp ) ) {
	$ini = @parse_ini_file( $fp, true );

	if ( !isset( $ini ) || !is_array( $ini ) ) {
		echo "Not a parseable ini file" . PHP_EOL;
	}

	if ( isset( $ini[ "server" ] ) && isset( $ini[ "server" ][ "instanceUrl" ] ) ) {
		if ( isset( $ini[ "server" ][ "generateDirectory" ] ) && !empty( $ini[ "server" ][ "generateDirectory" ] ) ) {
			// Steal the database config
			$users = [];
			if ( !isset( $ini[ "server" ][ "sqliteDb" ] ) || empty( $ini[ "server" ][ "sqliteDb" ] ) ) {
				echo "This script only works with a valid sqlite db" . PHP_EOL;
				exit;
			} else {
				$db = new SQLite3( $ini[ "server" ][ "sqliteDb" ] );
				$sql = "SELECT a.url FROM users AS u INNER JOIN accounts AS a ON u.account_id = a.id WHERE u.disabled = false AND u.approved = true ORDER BY a.display_name ASC";
				$q = $db->query( $sql );
				while ( $res = $q->fetchArray() ) {
					$users[] = $res;
				}

				$db->close();
			}

			// Steal the index template
			$opts = [
				"http" => [
					"method" => "GET",
					"header" => "Accept-language: en\r\nUser-agent: gotosocial-oncaffeine (Teapot-avoider 1.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36\r\n"
				]
			];

			$context = stream_context_create( $opts );
			$template = @file_get_contents( $ini[ "server" ][ "instanceUrl" ], false, $context );
			if ( isset( $template ) && !empty( $template ) ) {

				libxml_use_internal_errors(true);

				$tdoc = new DOMDocument();
				$tdoc->loadHTML( $template, LIBXML_NOWARNING );
				libxml_clear_errors();

                                $main = $tdoc->getElementsByTagName( "main" );
                                if ( $main instanceof DOMNodeList && $main->length === 1 ) {
                                        // Clear it.
                                        $main[ 0 ]->nodeValue = "";
                                        $main[ 0 ]->textContent = "";

                                        if ( isset( $ini[ "server" ][ "generateDirectory" ] ) ) {
                                                $link = $tdoc->getElementsByTagName( "link" );
                                                if ( $link instanceof DOMNodeList && $link->length > 0 ) {
                                                        for ( $i = $link->length; $i > 0; $i-- ) {
                                                                $link[ $i - 1 ]->parentNode->removeChild( $link[ $i - 1 ] );
                                                        }
                                                }

                                                foreach ( $users as $profile ) {
                                                        $profilePage = @file_get_contents( $profile[ "url" ], false, $context );
                                                        $pdoc = new DOMDocument();
                                                        $pdoc->loadHTML( $profilePage, LIBXML_NOWARNING );
                                                        libxml_clear_errors();

                                                        $xp = new DomXPath( $pdoc );
                                                        $profileNode = $xp->query( "//*[contains(@class,'profile')]" );
                                                        if ( $profileNode instanceof DOMNodeList && $profileNode->length === 1 ) {
                                                                $main[ 0 ]->appendChild( $tdoc->importNode( $profileNode[ 0 ], true ) );
                                                        }
                                                }

                                                if ( isset( $pdoc ) && $pdoc instanceof DOMDocument ) {
                                                        $link = $pdoc->getElementsByTagName( "link" );
                                                        $title = $tdoc->getElementsByTagName( "title" );

                                                        if (    $link instanceof DOMNodeList && $link->length > 0 &&
                                                                $title instanceof DOMNodeList && $title->length === 1 )
                                                        {
                                                                foreach ( $link as $linkel ) {
                                                                        $title[ 0 ]->parentNode->insertBefore( $tdoc->importNode( $linkel, true ), $title[ 0 ] );
                                                                }
                                                        }
                                                }
                                        }
                                }

				file_put_contents( $ini[ "server" ][ "generateDirectory" ], $tdoc->saveHTML() );
			}
		}
	} else {
		echo "Missing instanceUrl" . PHP_EOL;
	}

} else {
	echo "Not a readable ini file" . PHP_EOL;
}
?>
