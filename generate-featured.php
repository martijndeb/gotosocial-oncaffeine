<?php
if ( count( $argv ) < 2 || substr( $argv[ 1 ], -4 ) !== ".ini" ) {
	echo "Usage: php generate-featured.php <ini file>" . PHP_EOL;
	exit;
}

$fp = realpath( $argv[ 1 ] );
if ( file_exists( $fp ) && is_readable( $fp ) ) {
	$ini = @parse_ini_file( $fp, true );

	if ( !isset( $ini ) || !is_array( $ini ) ) {
		echo "Not a parseable ini file" . PHP_EOL;
	}

	if ( isset( $ini[ "server" ] ) && isset( $ini[ "server" ][ "instanceUrl" ] ) ) {
		if ( isset( $ini[ "server" ][ "generateFeatured" ] ) && !empty( $ini[ "server" ][ "generateFeatured" ] ) ) {
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

					if ( isset( $ini[ "featured" ] ) && isset( $ini[ "featured" ][ "post" ] ) && is_array( $ini[ "featured" ][ "post" ] ) ) {
						foreach ( $ini[ "featured" ][ "post" ] as $postUrl ) {
							$postPage = @file_get_contents( $postUrl, false, $context );
							$pdoc = new DOMDocument();
							$pdoc->loadHTML( $postPage, LIBXML_NOWARNING );
							libxml_clear_errors();

							$xp = new DomXPath( $pdoc );
							$postNode = $xp->query( "//*[contains(@class,'toot expanded')]" );
							if ( $postNode instanceof DOMNodeList && $postNode->length === 1 ) {
								$main[ 0 ]->appendChild( $tdoc->importNode( $postNode[ 0 ], true ) );
							}

							unset($xp);
							unset($pdoc);
						}
					}
				}

				file_put_contents( $ini[ "server" ][ "generateFeatured" ], $tdoc->saveHTML() );
			}
		}
	} else {
		echo "Missing instanceUrl" . PHP_EOL;
	}

} else {
	echo "Not a readable ini file" . PHP_EOL;
}
?>