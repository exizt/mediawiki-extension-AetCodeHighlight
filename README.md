# AetCodeHighlight

Overview
* Extensions to help highlight source code syntax on media wiki.
* Link: https://github.com/exizt/mediawiki-extension-AetCodeHighlight.git
* Git: git@github.com:exizt/mediawiki-extension-AetCodeHighlight.git
* using CDN of `highlight.js`/`prismjs`.


## Requirements
* PHP 7.4.3 or later (tested up to 7.4.30)
* MediaWiki 1.35 or later (tested up to 1.35)


## cloning a repository
```shell
git clone git@github.com:exizt/mediawiki-extension-AetCodeHighlight.git AetCodeHighlight
```


## Installation
1. Download and place the files in a directory called `AetCodeHighlight` in your `extensions/` folder.
2. Add the following code at the bottom of your `LocalSettings.php`:
```
wfLoadExtension( 'AetCodeHighlight' );
```


## Configuration
- `$wgAetCodeHighlight['type']`
    - Choose from the Syntax Highlight feature.
        - value : `'highlightjs'` or `'prismjs'`
        - default : `'highlightjs'`



## Usage
using `source` tag or `scode` tag or `syntaxhighlight` tag.

