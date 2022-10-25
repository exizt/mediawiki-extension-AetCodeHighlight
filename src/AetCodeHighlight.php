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

	/**
	 * 
	 * 참고
	 * https://www.mediawiki.org/wiki/Manual:Tag_extensions
	 * https://github.com/wikimedia/mediawiki-extensions-SyntaxHighlight_GeSHi/blob/master/includes/SyntaxHighlight.php
	 */
	public static function parserHook( $text, array $args, Parser $parser, PPFrame $frame ){
		self::debugLog('parserHook');

		# 설정값 조회
		$config = self::getConfiguration();

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

			$wrapDivClassList = ['mw-ext-codehighlight'];
			if(self::isPrismJS($config)){
				$wrapDivClassList[] = 'mw-ext-codehighlight-prismjs';
			} elseif(self::isHighlightJS($config)){
				$wrapDivClassList[] = 'mw-ext-codehighlight-highlightjs';
			}
			$wrapDivClass = implode(' ', $wrapDivClassList);
			

			$output = Html::openElement( 'div' , ['class' => $wrapDivClass]) .
				$output .
				Html::closeElement( 'div' );
		}

		# 모듈 또는 cdn 처리
		# ParserOutput
		$parserOutput = $parser->getOutput();
		if( $config['lazy'] ){
			$modules[] = 'ext.CodeHighlight';
			$parserOutput->addModules( $modules );
		} else {
			if ( $config['type'] == self::TYPE_PRISM_JS ){
				$html = self::makePrismJsHTML();
			} else {
				$html = self::makeHighlightJsHTML();
			}
			$parserOutput->addHeadItem($html, 'aet-codehighlight');
			$parserOutput->addModuleStyles( ['ext.CodeHighlight.styles'] );
		}

		return $output;
	}

	/**
	 * `Highlight.js`를 호출하는 HTML 태그
	 * @see https://highlightjs.org/usage/
	 */
	private static function makeHighlightJsHTML(){
		$html = <<<EOT
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/styles/atom-one-dark.min.css" integrity="sha512-Jk4AqjWsdSzSWCSuQTfYRIF84Rq/eV0G2+tu07byYwHcbTGfdmLrHjUSwvzp5HvbiqK4ibmNwdcG49Y5RGYPTg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
		<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/highlight.min.js" integrity="sha512-gU7kztaQEl7SHJyraPfZLQCNnrKdaQi5ndOyt4L4UPL/FHDd/uB9Je6KDARIqwnNNE27hnqoWLBq+Kpe4iHfeQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
		<script>hljs.highlightAll();</script>
		EOT;
		return $html;
	}

	/**
	 * `Prism.js`를 호출하는 HTML 태그
	 * @see https://prismjs.com/
	 * @see https://cdnjs.com/libraries/prism
	 */
	private static function makePrismJsHTML(){
		$html = <<<EOT
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css" integrity="sha512-mIs9kKbaw6JZFfSuo+MovjU+Ntggfoj8RwAmJbVXQ5mkAX5LlgETQEweFPI18humSPHymTb5iikEOKWF7I8ncQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
		<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js" integrity="sha512-7Z9J3l1+EYfeaPKcGXu3MS/7T+w19WtKQY/n+xzmw4hZhJ9tyYmcUS+4QqAlzhicE5LAfMQSF3iFTK9bQdTxXg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
		EOT;
		return $html;
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

	private static function isHighlightJS($config) {
		return $config['type'] == self::TYPE_HIGHLIGHT_JS;
	}

	private static function isPrismJS($config){
		return $config['type'] == self::TYPE_PRISM_JS;
	}

	/**
	 * '사용 안 함'을 설정.
	 */
	private static function setDisabled(){
		self::$_isAvailable = false;
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
		
		/*
		* 설정 기본값
		* 
		* type : 'highlightjs', 'prismjs'
		* theme : 테마
		*/
		$defaultConfig = [
			'type' => self::TYPE_HIGHLIGHT_JS,
			'lazy' => false,
			'debug' => false
		];
		
		# 설정값 병합
		$config = self::getUserLocalSettings();
		if (isset($config)){
			if($config['type'] != self::TYPE_HIGHLIGHT_JS && $config['type'] != self::TYPE_PRISM_JS){
				unset($config['type']);
			}
			self::debugLog('isset $wgCodeHighlight');
			$config = array_merge($defaultConfig, $config);
		} else {
			$config = $defaultConfig;
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
