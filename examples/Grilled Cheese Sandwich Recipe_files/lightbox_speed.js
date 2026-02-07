var DIGIOH_LOADER = DIGIOH_LOADER || {};
(function (digioh_loader) {
    if (digioh_loader.loaded) { return; }
    digioh_loader.loaded = true;
    digioh_loader.loads = digioh_loader.loads || {};
    function getPromise (doc) {
        let ld = digioh_loader.loads;
        if (ld[doc]) {
            return ld[doc];
        }
        let p = {};
        p.promise = new Promise((res, rej) => {
            p.resolveCallback = res;
            p.rejectCallback = rej;
        });
        ld[doc] = p;
        return p;
    }
    digioh_loader.getPromise = getPromise;
    const srcPath = "//forms.allrecipes.com/w37htfhcq2/vendor/47b4a4dd-ffd7-43db-8118-8002b7c15352/";

    function createScriptElement(src, id) {
        var e = document.createElement('script');
        e.type = 'text/javascript';
        e.async = true;
        e.fetchPriority = "high";
        e.src = src;
        if (id) { e.id = id; }
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(e, s);
    }

    function loadScript(doc, filename, cb) {
        if (!digioh_loader.loads[doc]) {
            getPromise(doc);
            let src = `${srcPath}${filename}?cb=${cb}`;
            createScriptElement(src);
        }
    };

    function sendPV() {
        try {
            window.SENT_LIGHTBOX_PV = true;

            var hn = 'empty';
            if (window && window.location && window.location.hostname) {
                hn = window.location.hostname;
            }

            var i = document.createElement("img");
            i.width = 1;
            i.height = 1;
            i.src = ('https://forms.allrecipes.com/w37htfhcq2/z9g/digibox.gif?c=' + (new Date().getTime()) + '&h=' + encodeURIComponent(hn) + '&e=p&u=44449');
        }
        catch (e) {
        }
    };

    function jsonp(src, callback) {
        const id = "__dgo" + Math.random().toString(36).replace(/^0\./, "");
        const prefix = src.includes("?") ? "&" : "?";
        src += `${prefix}callback=${id}`;
        window[id] = (data) => {
            document.querySelector(`#${id}`)?.remove();
            delete window[id];
            callback(data);
        };
        createScriptElement(src, id);
    }

    function initApi() {
        let c = localStorage.getItem("dgdr") || sessionStorage.getItem("dgdr");
        if (c) {
            if (c.match(/^{.*"env_ver":\s?"digioh-.*}$/i)) {
                const json = JSON.parse(c);
                if (!json.expires || json.expires > Date.now()) {
                    return;
                }
            }
            else {
                return;
            }
        }
        let p = getPromise("dgdr");
        let hostname = window?.location?.hostname || 'empty';
        let uri = `https://forms.allrecipes.com/a4flkt7l2b/z9gd/47b4a4dd-ffd7-43db-8118-8002b7c15352/${hostname}/jsonp/z?cb=${Date.now()}`;
        if (/^true$/.test("false")) {
            uri += "&skip_geo=true";
        }
        jsonp(uri, p.resolveCallback);
    }

    sendPV();
    initApi();

    const qaMode = (window.sessionStorage.getItem('xdibx_boxqamode') == 1 || window.location.href.indexOf('boxqamode') > 0);
    if (qaMode) {
        loadScript("user", "user_qa.js", "639060002119179428");
    loadScript("custom", "custom_qa.js", "DDDE7733E4EB41A57B5D9CBDCF6FFE2A");
    loadScript("main", "main_qa.js", "3E6039591D5E292EE292E054C194AA09");
    }
    else {
        loadScript("user", "user.js", "639060002119179428");
    loadScript("custom", "custom.js", "A87578683F69790CFC6F63692D21443A");
    loadScript("main", "main.js", "B7FCC90CCA25F3537E23F34F5B927745");
    }

})(DIGIOH_LOADER);