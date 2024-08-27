<?php
if (!defined('MEDIAWIKI')) {
    die('This is an extension to the MediaWiki software and cannot be used standalone.');
}

use MediaWiki\MediaWikiServices;

class PDFRender {

    public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {

        $out->addHeadItem(
            'swiper-js',
            '<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>'
        );
        $out->addScript('<script src="https://mozilla.github.io/pdf.js/build/pdf.mjs" type="module"></script>');
        $script = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">';
        $out->addHeadItem('swiper-css', $script);
          
    }
    public static function onParserFirstCallInit(Parser $parser) {
        // Register the parser hook for <pdfembed> tag
        $parser->setHook('pdfembed', [self::class, 'generateTag']);
    }

     /**
     * disable the cache
     *
     * @param Parser $parser
     */
    static public function disableCache(Parser &$parser)
    {
        // see https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/MagicNoCache/+/refs/heads/master/src/MagicNoCacheHooks.php
        global $wgOut;
        $parser->getOutput()->updateCacheExpiry(0);

        if (method_exists($wgOut, 'disableClientCache')) {
            $wgOut->disableClientCache();
        } else {
            $wgOut->enableClientCache(false);
        }
    }

    /**
     * remove the File: prefix depending on the language or in english default form
     *
     * @param
     *            filename - the filename for which to fix the prefix
     * @return    string - the filename without the File: / Media: or i18n File/Media prefix
     */
    static public function removeFilePrefix($filename): string
    {
        $mwServices = MediaWikiServices::getInstance();

        if (method_exists($mwServices, "getContentLanguage")) {
            $contentLang = $mwServices->getContentLanguage();

            # there are four possible prefixes: 'File' and 'Media' in English and in the wiki's language
            $ns_media_wiki_lang = $contentLang->getFormattedNsText(NS_MEDIA);
            $ns_file_wiki_lang  = $contentLang->getFormattedNsText(NS_FILE);

            if (method_exists($mwServices, "getLanguageFactory")) {
                $langFactory = $mwServices->getLanguageFactory();
                $lang = $langFactory->getLanguage('en');
                $ns_media_lang_en = $lang->getFormattedNsText(NS_MEDIA);
                $ns_file_lang_en  = $lang->getFormattedNsText(NS_FILE);
                $filename = preg_replace("/^($ns_media_wiki_lang|$ns_file_wiki_lang|$ns_media_lang_en|$ns_file_lang_en):/", '', $filename);
            } else {
                $filename = preg_replace("/^($ns_media_wiki_lang|$ns_file_wiki_lang):/", '', $filename);
            }
        }
        return $filename;
    }



    /**
     * Generates the PDF object tag.
     *
     * @access public
     * @param
     *            string Namespace prefixed article of the PDF file to display.
     * @param
     *            array Arguments on the tag.
     * @param
     *            object Parser object.
     * @param
     *            object PPFrame object.
     * @return string HTML
     */
    static public function generateTag($obj, $args = [], ?Parser $parser=null, ?PPFrame $frame=null): string
    {
        $parser->getOutput()->addModules(['ext.PDFRender']);
        global $wgPdfEmbed, $wgRequest, $wgPDF;
        // disable the cache
        PDFEmbed::disableCache($parser);

        // grab the uri by parsing to html
        $html = $parser->recursiveTagParse($obj, $frame);

        // check the action which triggered us
        $requestAction = $wgRequest->getVal('action');

        if ($requestAction === null) {
            // https://www.mediawiki.org/wiki/Manual:UserFactory.php
            $revUserName = $parser->getRevisionUser();
            if (empty($revUserName)) {
                return self::error('embed_pdf_invalid_user');
            }

            $userFactory = MediaWikiServices::getInstance()->getUserFactory();
            $user = $userFactory->newFromName($revUserName);
        }

        // depending on the action get the responsible user
        if ($requestAction === 'edit' || $requestAction === 'submit') {
            $user = RequestContext::getMain()->getUser();
        }

        if (!($user instanceof User &&
              MediaWikiServices::getInstance()->getPermissionManager()->userHasRight($user, 'embed_pdf')
        )) {
            $parser->addTrackingCategory("pdfembed-permission-problem-category");
            return self::error('embed_pdf_no_permission', wfMessage('right-embed_pdf'));
        }

        // we don't want the html but just the href of the link
        // so we might reverse some of the parsing again by examining the html
        // whether it contains an anchor <a href= ...
        if (strpos($html, '<a') !== false) {
            $anchor = new SimpleXMLElement($html);
            // is there a href element?
            if (isset($anchor['href'])) {
                // that's what we want ...
                $html = $anchor['href'];
            }
        }

        if (array_key_exists('width', $args)) {
            $widthStr = $parser->recursiveTagParse($args['width'], $frame);
        } else {
            $widthStr = $wgPdfEmbed['width'];
        }

        if (array_key_exists('height', $args)) {
            $heightStr = $parser->recursiveTagParse($args['height'], $frame);
        } else {
            $heightStr = $wgPdfEmbed['height'];
        }
        if (!preg_match('~^\d+~', $widthStr)) {
            return self::error("embed_pdf_invalid_width", $widthStr);
        } elseif (!preg_match('~^\d+~', $heightStr)) {
            return self::error("embed_pdf_invalid_height", $heightStr);
        }
        # if there are no slashes in the name we assume this
        # might be a pointer to a file
        if (preg_match('~^([^\/]+\.pdf)(#[0-9]+)?$~', $html, $matches)) {
            # re contains the groups
            $filename = $matches[1];
            if (count($matches) == 3) {
                $page = $matches[2];
            }

            $filename = self::removeFilePrefix($filename);
            $pdfFile = MediaWikiServices::getInstance()->getRepoGroup()->findFile($filename);

            if ($pdfFile !== false) {
                $url = $pdfFile->getFullUrl();
                return self::embed($url);
            } else {
                return self::error('embed_pdf_invalid_file', $filename);
            }
        } else {
            // parse the given url
            $domain = parse_url($html);

            // check that the parsing worked and retrieve a valid host
            // no relative urls are allowed ...
            if ($domain === false || (!isset($domain['host']))) {
                if (!isset($domain['host'])) {
                    return self::error("embed_pdf_invalid_relative_domain", $html);
                }
                return self::error("embed_pdf_invalid_url", $html);
            }

            if (isset($wgPDF)) {

                foreach ($wgPDF['black'] as $x => $y) {
                    $wgPDF['black'][$x] = strtolower($y);
                }
                foreach ($wgPDF['white'] as $x => $y) {
                    $wgPDF['white'][$x] = strtolower($y);
                }

                $host = strtolower($domain['host']);
                $whitelisted = false;

                if (in_array($host, $wgPDF['white'])) {
                    $whitelisted = true;
                }

                if ($wgPDF['white'] != array() && !$whitelisted) {
                    return self::error("embed_pdf_domain_not_white", $host);
                }

                if (!$whitelisted) {
                    if (in_array($host, $wgPDF['black'])) {
                        return self::error("embed_pdf_domain_black", $host);
                    }
                }
            }

            # check that url is valid
            if (filter_var($html, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                return self::embed($html);
            } else {
                return self::error('embed_pdf_invalid_url', $html);
            }
        }
    }

    /**
     * Returns an HTML node for the given file as string.
     *
     * @access private
     * @param
     *            URL url to embed.
     * @return string HTML code.
     */
    static private function embed($url): string
    {
        # secure and concatenate the url
        $pdfSafeUrl = htmlentities($url);
        
        $output = ' <div class="pdf-carousel-container">';
        $output .= '    <div class="loading-spinner"></div>';
        $output .= '    <div class="swiper-container pdf-carousel" data-pdf-url="' . htmlspecialchars($pdfSafeUrl) . '">
                            <div class="swiper-wrapper"></div>
                            <div class="swiper-pagination"></div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                    </div>';

        return $output;
    }

    /**
     * Returns a standard error message.
     *
     * @access private
     * @param
     *            string Error message key to display.
     * @param
     *            params any parameters for the error message
     * @return string HTML error message.
     */
    static private function error($messageKey, ...$params): string
    {
        return Xml::span(wfMessage($messageKey, $params)->plain(), 'error');
    }




    // public static function renderPDF($input, array $args, Parser $parser, PPFrame $frame) {
    //     // Load the ResourceLoader module
    //     $parser->getOutput()->addModules(['ext.PDFRender']);
	//     // $pdfJsPath ='/vendor/pdfjs/pdf.mjs';
	//     // $parser->getOutput()->addScriptFile($pdfJsPath);
	//     // $pdfWorkerJsPath ='/vendor/pdfjs/pdf.worker.mjs';
	//     // $parser->getOutput()->addScriptFile($pdfWorkerJsPath);
    //     // PDF.js viewer container
    //     $output = '<div class="pdf-container" data-pdf="' .$input. '">';
    //     $output .= '<canvas></canvas>';
    //     $output .= '</div>';

    //     return $output;
    // }
}
