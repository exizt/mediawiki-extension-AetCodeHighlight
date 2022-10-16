<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

class EzxCodeHighlight {
	// 설정값을 갖게 되는 멤버 변수
	private static $config;

	/**
	 * 참고
	 * https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * 
	 */
	public static function onParserFirstCallInit( Parser $parser ){
		// $parser->setHook( $tag, callable $callback )
		$parser->setHook('source', [self::class, 'parserHook']);
		$parser->setHook('syntaxhighlight', [self::class, 'parserHook']);
	}

	/**
	 * 
	 * 참고
	 * https://www.mediawiki.org/wiki/Manual:Tag_extensions
	 * https://github.com/wikimedia/mediawiki-extensions-SyntaxHighlight_GeSHi/blob/master/includes/SyntaxHighlight.php
	 */
	public static function parserHook( $text, array $args, Parser $parser, PPFrame $frame ){
		// Replace strip markers (For e.g. {{#tag:syntaxhighlight|<nowiki>...}})
		$output = $parser->getStripState()->unstripNoWiki( $text ?? '' );

		// Don't trim leading spaces away, just the linefeeds
		$output = preg_replace( '/^\n+/', '', rtrim( $output ) );
		$output = htmlspecialchars($output);

		$lang = $args['lang'] ?? '';

		$isInline = isset( $args['inline'] );

		// Allow certain HTML attributes
		$htmlAttribs = Sanitizer::validateAttributes(
			$args, array_flip( [ 'style', 'class', 'id' ] )
		);

		// Build class list
		$classList = [];
		if ( isset( $htmlAttribs['class'] ) ) {
			$classList[] = $htmlAttribs['class'];
		}
		if ( !empty($lang) ){
			$classList[] = 'language-'.$lang;
		}

		$htmlAttribs['class'] = implode( ' ', $classList );

		// 
		if ( $isInline ) {
			// We've already trimmed the input $code before highlighting,
			// but pygment's standard out adds a line break afterwards,
			// which would then be preserved in the paragraph that wraps this,
			// and become visible as a space. Avoid that.
			$output = trim( $output );

			// Enforce inlineness. Stray newlines may result in unexpected list and paragraph processing
			// (also known as doBlockLevels()).
			$output = str_replace( "\n", ' ', $output );
			$output = Html::rawElement( 'code', $htmlAttribs, $output );
		} else {
			// $output = self::unwrap( $output );
			// 태그를 보호하는 구문이 여기에 있어야 할 듯.

			if ( $parser ) {
				// 이 부분은 아직 잘 이해가 안 감.
				// Use 'nowiki' strip marker to prevent list processing (also known as doBlockLevels()).
				// However, leave the wrapping <div/> outside to prevent <p/>-wrapping.
				$marker = $parser::MARKER_PREFIX . '-codesyntaxhighlight-' .
					sprintf( '%08X', $parser->mMarkerIndex++ ) . $parser::MARKER_SUFFIX;
				$parser->getStripState()->addNoWiki( $marker, $output );
				$output = $marker;
			}

			$output = Html::openElement( 'code', $htmlAttribs ) .
			$output .
			Html::closeElement( 'code' );


			$output = Html::openElement( 'pre' ) .
				$output .
				Html::closeElement( 'pre' );

			$output = Html::openElement( 'div' ) .
				$output .
				Html::closeElement( 'div' );
		}
		return $output;
	}

	/**
	 * 설정을 로드함.
	 */
	public static function getConfiguration(){
		# 한 번 로드했다면, 그 후에는 로드하지 않도록 처리.
		if(is_array(self::$config)){
			return self::$config;
		}
		self::debugLog('::getConfiguration');
		global $wgCodeHighlight;

		/*
		* 설정 기본값
		* 
		* ClientId : 애드센스 id key 값. (예: ca-pub-xxxxxxxxx)
		* SlotIdContentTop : 콘텐츠 상단에 표시할 애드센스 광고 단위 아이디 (예: xxxxxxx)
		* SlotIdContentBottom : 콘텐츠 히단에 표시할 애드센스 광고 단위 아이디 (예: xxxxxxx)
		* AnonOnly : '비회원'만 애드센스 노출하기.
		* DisallowedIPs : 애드센스를 보여주지 않을 IP 목록.
		*/
		$config = [
			'Type' => 'highlightjs',
			'Debug' => false
		];
		
		# 설정값 병합
		if (isset($wgCodeHighlight)){
			self::debugLog('isset $wgCodeHighlight');
			$config = array_merge($config, $wgCodeHighlight);
		}

		self::$config = $config;
		return $config;
	}

	/**
	 * 옵션이 지정되어있는지 여부
	 * 
	 * @return boolean false (지정되지 않았음) /true(지정되어 있음)
	 */
	private static function isOptionSet($config, $name){
		if( !isset($config[$name]) ){
			return false;
		}
		if($config[$name] === '' || $config[$name] === 'none'
		 || $config[$name] === false || $config[$name] === NULL){
			return false;
		}
		return true;
	}

	/**
	 * 디버그 로깅 관련
	 */
	private static function debugLog($msg){
		global $wgDebugToolbar, $wgCodeHighlight;

		# 디버그툴바 사용중일 때만 허용.
		$useDebugToolbar = $wgDebugToolbar ?? false;
		if( !$useDebugToolbar ){
			return false;
		}

		// 디버깅 여부
		if(is_array(self::$config)){
			$isDebug = self::$config['Debug'];
		} else {
			$isDebug = $wgCodeHighlight['Debug'] ?? false;
		}

		// 로깅
		if($isDebug){
			$debugTag = 'EzxCodeHighlight';
			if(is_string($msg)){
				wfDebugLog($debugTag, $msg);
			} else if(is_object($msg) || is_array($msg)){
				wfDebugLog($debugTag, json_encode($msg));
			} else {
				wfDebugLog($debugTag, json_encode($msg));
			}
		} else {
			return false;
		}
	}
}
