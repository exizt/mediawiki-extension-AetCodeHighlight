<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */
use Html;
use Parser;
use Sanitizer;

class AetCodeHighlight {
	# 설정값을 갖게 되는 멤버 변수
	private static $config = null;

	# 이용 가능한지 여부 (isAvailable 메소드에서 체크함)
	private static $_isAvailable = true;
	
	# 상수들
	const TYPE_PRISM_JS = 'prismjs';
	const TYPE_HIGHLIGHT_JS = 'highlightjs';

	/**
	 * 'onParserFirstCallInit' 훅의 진입점
	 * 
	 * 참고
	 * https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 */
	public static function onParserFirstCallInit( Parser $parser ){
		// $parser->setHook( $tag, callable $callback )
		$parser->setHook('source', [self::class, 'parserHook']);
		$parser->setHook('syntaxhighlight', [self::class, 'parserHook']);
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ){
		$modules[] = 'ext.CodeHighlight';
		$out->addModules( $modules );
	}

	/**
	 * 
	 * 참고
	 * https://www.mediawiki.org/wiki/Manual:Tag_extensions
	 * https://github.com/wikimedia/mediawiki-extensions-SyntaxHighlight_GeSHi/blob/master/includes/SyntaxHighlight.php
	 */
	public static function parserHook( $text, array $args, Parser $parser, PPFrame $frame ){
		// Replace strip markers (For e.g. {{#tag:syntaxhighlight|<nowiki>...}})
		$text = $parser->getStripState()->unstripNoWiki( $text ?? '' );

		// 해당하는 내용의 특수문자 처리 및 attributes 확인
		$result = self::processContent($text, $args);

		// 소스 문자열
		$output = $result['output'] ?? '';

		// 설정된 소스 언어
		$lang = $result['lang'] ?? '';

		// inline 속성 여부
		$isInline = $result['inline'] ?? false;

		// style, class, id 속성값
		$htmlAttribs = $result['attrib'] ?? [];

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
			// Enforce inlineness. Stray newlines may result in unexpected list and paragraph processing
			// (also known as doBlockLevels()).
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

			$output = Html::openElement( 'div' , ['class'=>'mw-ext-codehighlight']) .
				$output .
				Html::closeElement( 'div' );
		}
		return $output;
	}

	/**
	 * 
	 * 참고
	 * https://github.com/wikimedia/mediawiki-extensions-SyntaxHighlight_GeSHi/blob/master/includes/SyntaxHighlight.php
	 */
	public static function processContent( $text, array $args){
		// 앞부분 \n 값 삭제 및, 뒷 부분 공백 삭제
		$output = preg_replace( '/^\n+/', '', rtrim( $text ) );

		// 특수문자(<> 등)를 HTML entities로 치환.
		$output = htmlspecialchars($output);

		$lang = $args['lang'] ?? '';

		// inline 타입인지
		$isInline = isset( $args['inline'] );

		// Allow certain HTML attributes
		// style, class, id 속성값이 있을 경우 나눠서 담음.
		$htmlAttribs = Sanitizer::validateAttributes(
			$args, array_flip( [ 'style', 'class', 'id' ] )
		);

		// 
		if ( $isInline ) {
			// inline 형태일 때, 앞부분의 공백도 제거.
			$output = trim( $output );
			
			// 중간의 \n도 제거.
			$output = str_replace( "\n", ' ', $output );
		}

		return [
			'output' => $output,
			'lang' => $lang,
			'inline' => $isInline,
			'attrib' => $htmlAttribs
		];
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
		$wgCodeHighlight = self::getUserLocalSettings();

		/*
		* 설정 기본값
		* 
		* type : 'highlightjs', 'prismjs'
		* theme : 테마
		*/
		$config = [
			'type' => self::TYPE_HIGHLIGHT_JS,
			'debug' => false
		];

		if($wgCodeHighlight['type'] != self::TYPE_HIGHLIGHT_JS && $wgCodeHighlight['type'] != self::TYPE_PRISM_JS){
			unset($wgCodeHighlight['type']);
		}
		
		# 설정값 병합
		if (isset($wgCodeHighlight)){
			self::debugLog('isset $wgCodeHighlight');
			$config = array_merge($config, $wgCodeHighlight);
		}

		self::$config = $config;
		return $config;
	}

	/**
	 * 설정값 조회
	 */
	private static function getUserLocalSettings(){
		global $wgCodeHighlight;
		return $wgCodeHighlight;
	}

	/**
	 * 디버그 로깅 관련
	 */
	private static function debugLog($msg){
		global $wgDebugToolbar;

		# 디버그툴바 사용중일 때만 허용.
		$useDebugToolbar = $wgDebugToolbar ?? false;
		if( !$useDebugToolbar ){
			return false;
		}
		
		# 로깅
		$userSettings = self::getUserLocalSettings();
		$isDebug = $userSettings['debug'] ?? false;
		if($isDebug){
			if(is_string($msg)){
				wfDebugLog(static::class, $msg);
			} else {
				wfDebugLog(static::class, json_encode($msg));
			}
		} else {
			return false;
		}
	}
}
