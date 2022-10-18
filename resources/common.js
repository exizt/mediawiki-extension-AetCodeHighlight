(function () {
    initCodeHighlight()
    /*
    mw.hook('wikipage.content').add(initCodeHighlightHook);

    function initCodeHighlightHook($content){
        var check = document.querySelector('body.action-view') !== null;
		if(!check){
            return
        }
        
        check = document.querySelector('.mw-parser-output') !== null;
		if(!check){
            return
        }

        check = document.querySelector('.mw-ext-codehighlight') !== null;
		if(!check){
            return
        }

        console.log('dddd')
        initCodeHighlight()
    }
    */

    function initCodeHighlight(){
        addScript('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/highlight.min.js',
            'sha512-gU7kztaQEl7SHJyraPfZLQCNnrKdaQi5ndOyt4L4UPL/FHDd/uB9Je6KDARIqwnNNE27hnqoWLBq+Kpe4iHfeQ==', 
            onLoad, true)

        addStyle('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/styles/atom-one-dark.min.css',
            'sha512-Jk4AqjWsdSzSWCSuQTfYRIF84Rq/eV0G2+tu07byYwHcbTGfdmLrHjUSwvzp5HvbiqK4ibmNwdcG49Y5RGYPTg==')
    }

    /**
     * 실행순서에 대한 고민이 있었는데.. 이 경우에는 미디어위키의 확장 기능으로 동작되어야 하는데에
     * 걸리는 시간이 제법 있으므로... 크게 신경쓰지 않아도 된다. 상당히 후순위에서 실행이 된다...
     */
    function onLoad(){
        //mw.hook('wikipage.content').add(onLoadCodeHighlight)
        // mw.hook('wikipage.content').add(onLoadCodeHighlight)
        // document.addEventListener('DOMContentLoaded', onLoadCodeHighlight)

        onLoadCodeHighlight();
        function onLoadCodeHighlight(){
            console.log('ccccc')


            var check = document.querySelector('body.action-view') !== null;
            if(!check){
                return
            }
            
            check = document.querySelector('.mw-parser-output') !== null;
            if(!check){
                return
            }
    
            check = document.querySelector('.mw-ext-codehighlight') !== null;
            if(!check){
                return
            }
    
            console.log('ccccc2')

            hljs.highlightAll();
        }
    }
    

    function addScript(link, integrity = '', onload = null, isDefer=false){
        var script = document.createElement('script')
        script.src = link
        if(integrity != ''){
            script.integrity = integrity
        }
        script.crossOrigin = 'anonymous'
        script.referrerPolicy = 'no-referrer'
        if (typeof onload === 'function'){
            script.onload = onload
        }
        if(isDefer) script.defer = true
        
        // add
        document.head.appendChild(script)
    }
    function addStyle(link, integrity = ''){
        var style = document.createElement('link')
        style.type = 'text/css';
        style.rel = 'stylesheet';
        style.href = link
        if(integrity != ''){
            style.integrity = integrity
        }
        style.crossOrigin = 'anonymous'
        style.referrerPolicy = 'no-referrer'
        // add
        document.head.appendChild(style)
    }
})();