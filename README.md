# AetCodeHighlight

개요
* 미디어위키에 소스 코드 구문 강조 기능을 도와주는 확장 기능.
* Git : hhttps://github.com/exizt/mw-ext-AetCodeHighlight
* `highlight.js`의 CDN을 이용해서 코드 구문 강조를 구현함.


## Requirements
* PHP 7.4.3 or later (tested up to 7.4.30)
* MediaWiki 1.35 or later (tested up to 1.35)


## cloning a repository
```shell
git clone git@github.com:exizt/mw-ext-AetCodeHighlight.git AetCodeHighlight
```


## Installation
1. Download and place the files in a directory called `AetCodeHighlight` in your `extensions/` folder.
2. Add the following code at the bottom of your `LocalSettings.php`:
```
wfLoadExtension( 'AetCodeHighlight' );
```


## Configuration
- `$wgAetCodeHighlight['type']`
    - 구문 강조 기능 중 선택. 
        - value : `'highlightjs'` or `'prismjs'`
        - default : `'highlightjs'`

