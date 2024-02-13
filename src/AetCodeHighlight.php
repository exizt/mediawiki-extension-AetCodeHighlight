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

    # 상수들
    const TYPE_PRISM_JS = 'prismjs';
    const TYPE_HIGHLIGHT_JS = 'highlightjs';

    /**
     * 'onParserFirstCallInit' 훅의 진입점
     *
     * @param Parser $parser
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
     */
    public static function onParserFirstCallInit( Parser $parser ){
        // $parser->setHook( $tag, callable $callback )
        // self::debugLog('onParserFirstCallInit');
        $parser->setHook('source', [self::class, 'parserHook']);
        $parser->setHook('scode', [self::class, 'parserHook']);
        $parser->setHook('syntaxhighlight', [self::class, 'parserHook']);
    }

    /**
     *
     * @param string $text
     * @param array $args
     * @param ?Parser $parser
     * @return array
     * @see https://www.mediawiki.org/wiki/Manual:Tag_extensions
     * @see https://github.com/wikimedia/mediawiki-extensions-SyntaxHighlight_GeSHi/blob/master/includes/SyntaxHighlight.php
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
        /*
        $html = <<<EOT
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/styles/atom-one-dark.min.css" integrity="sha512-Jk4AqjWsdSzSWCSuQTfYRIF84Rq/eV0G2+tu07byYwHcbTGfdmLrHjUSwvzp5HvbiqK4ibmNwdcG49Y5RGYPTg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/highlight.min.js" integrity="sha512-gU7kztaQEl7SHJyraPfZLQCNnrKdaQi5ndOyt4L4UPL/FHDd/uB9Je6KDARIqwnNNE27hnqoWLBq+Kpe4iHfeQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>hljs.highlightAll();</script>
        EOT;
        */

        $html = <<<EOT
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css" integrity="sha512-Jk4AqjWsdSzSWCSuQTfYRIF84Rq/eV0G2+tu07byYwHcbTGfdmLrHjUSwvzp5HvbiqK4ibmNwdcG49Y5RGYPTg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js" integrity="sha512-D9gUyxqja7hBtkWpPWGt9wfbfaMGVt9gnyCvYa+jojwwPHLCzUm5i8rpk7vD7wNee9bA35eYIjobYPaQuKS1MQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/dockerfile.min.js" integrity="sha512-y0uGK4Ql/eJrIn2uOu2Hfc/3wnQpAHlEF58pL7akgWaVnuOJ8D5Aal/VPRKyMGADVuAavg1yVdLUpn9PlnGmYA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/dos.min.js" integrity="sha512-01qE2gmXm4sOvO+4uWgyfFF4az4dGYpwDemly7IlyB6bAjoNeQhrH7RAdFujraSMuyoOPgoSB1DhbJY6P6dhFA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/gradle.min.js" integrity="sha512-u/m2Lx3pr7txqTNmT0WIW3iomkxTYXbh4RL7c3/Eg565qEU4YSeN/gbiFQ7VEoincBS60uQoGszBUgetBT51lA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/clojure.min.js" integrity="sha512-KaEPWVghlkqO036k5Mrh5xEYznAGnzWWx7fpcWVGTsLlCtcovEXZy7wOkQQJk8JLRWR0pAOTI38os+rhQxflxg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>
        hljs.registerAliases(["batch"],{ languageName: "dos" })
        hljs.highlightAll();
        </script>
        EOT;

        return $html;
    }

    /**
     * `Prism.js`를 호출하는 HTML 태그
     * @see https://prismjs.com/
     * @see https://cdnjs.com/libraries/prism
     */
    private static function makePrismJsHTML(){
        /*
        $html = <<<EOT
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css" integrity="sha512-mIs9kKbaw6JZFfSuo+MovjU+Ntggfoj8RwAmJbVXQ5mkAX5LlgETQEweFPI18humSPHymTb5iikEOKWF7I8ncQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js" integrity="sha512-7Z9J3l1+EYfeaPKcGXu3MS/7T+w19WtKQY/n+xzmw4hZhJ9tyYmcUS+4QqAlzhicE5LAfMQSF3iFTK9bQdTxXg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        EOT;
        */

        $html = <<<EOT
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css" integrity="sha512-mIs9kKbaw6JZFfSuo+MovjU+Ntggfoj8RwAmJbVXQ5mkAX5LlgETQEweFPI18humSPHymTb5iikEOKWF7I8ncQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js" integrity="sha512-9khQRAUBYEJDCDVP2yw3LRUQvjJ0Pjx0EShmaQjcHa6AXiOv6qHQu9lCAIR8O+/D8FtaCoJ2c0Tf9Xo7hYH01Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js" integrity="sha512-SkmBfuA2hqjzEVpmnMt/LINrjop3GKWqsuLSSB3e7iBmYK7JuWw4ldmmxwD9mdm2IRTTi0OxSAfEGvgEi0i2Kw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        EOT;

        return $html;
    }

    /**
     *
     * @param string $text
     * @param array $args
     * @see https://github.com/wikimedia/mediawiki-extensions-SyntaxHighlight_GeSHi/blob/master/includes/SyntaxHighlight.php
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
     * HighlightJs 사용 여부
     */
    private static function isHighlightJS($config) {
        return $config['type'] == self::TYPE_HIGHLIGHT_JS;
    }

    /**
     * prismJs 사용 여부
     */
    private static function isPrismJS($config){
        return $config['type'] == self::TYPE_PRISM_JS;
    }

    /**
     * 설정을 로드함.
     */
    public static function getConfiguration(){
        # 한 번 로드했다면, 그 후에는 로드하지 않도록 처리.
        if( !is_null(self::$config) ){
            return self::$config;
        }
        self::debugLog('::getConfiguration');

        /*
        * 설정 기본값
        *
        * type : 'highlightjs', 'prismjs'
        * theme : 테마
        */
        $config = [
            'type' => self::TYPE_HIGHLIGHT_JS,
            'lazy' => false,
            'debug' => false
        ];

        # 설정값 병합
        $userSettings = self::readSettings();
        if (isset($userSettings)){
            if( isset($userSettings['type']) ){
                if($userSettings['type'] != self::TYPE_HIGHLIGHT_JS && $userSettings['type'] != self::TYPE_PRISM_JS){
                    unset($userSettings['type']);
                }
            }
            # 만약을 위한 설정값 타입 체크.
            foreach ($userSettings as $key => $value) {
                if( array_key_exists($key, $config) ) {
                    if( gettype($config[$key]) == gettype($value) ){
                        $config[$key] = $value;
                    } else {
                        self::debugLog($key.'옵션값이 잘못되었습니다.');
                    }
                }
            }
        }

        self::debugLog($config);
        self::$config = $config;
        return $config;
    }

    /**
     * 전역 설정값 조회
     *
     * @return array|mixed 설정된 값 또는 undefined|null를 반환
     */
    private static function readSettings(){
        global $wgAetCodeHighlight;
        return $wgAetCodeHighlight;
    }

    /**
     * 디버그 로깅 관련
     *
     * @param string|object $msg 디버깅 메시지 or 오브젝트
     */
    private static function debugLog($msg){
        global $wgDebugToolbar;

        # 디버그툴바 사용중일 때만 허용.
        $isDebugToolbarEnabled = $wgDebugToolbar ?? false;
        if( !$isDebugToolbarEnabled ){
            return;
        }

        # 로깅
        $settings = self::readSettings() ?? [];
        $isDebug = $settings['debug'] ?? false;
        if($isDebug){
            if(is_string($msg)){
                wfDebugLog(static::class, $msg);
            } else {
                wfDebugLog(static::class, json_encode($msg));
            }
        }
    }
}
