<?php
/**
 * SubTranslate MediaWiki extension  version 1.0.2
 *  for details please see: https://www.mediawiki.org/wiki/Extension:SubTranslate
 *
 * Copyright (c) 2023 Kimagurenote https://kimagurenote.net/
 * License: Revised BSD license http://opensource.org/licenses/BSD-3-Clause
 */




// JUST SKELETON - I will not adjust this to my own needs !!!!



use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;

use MediaWiki\Html\Html;

class SubTranslate {

  /* accepted language codes and captions */
  static $targetLangs = [
  'BG' => "български език",  /* Bulgarian */
  'CS' => "český jazyk",  /* Czech */
  'DA' => "dansk",  /* Danish */
  'DE' => "Deutsch",  /* German */
  'EL' => "ελληνικά",  /* Greek */
  'EN' => "English",  /* English */  /* unspecified variant for backward compatibility; please select EN-GB or EN-US instead */
  'EN-GB' => "British English",  /* English (British) */
  'EN-US' => "American English",  /* English (American) */
  'ES' => "español",  /* Spanish */
  'ET' => "eesti keel",  /* Estonian */
  'FI' => "suomi",  /* Finnish */
  'FR' => "français",  /* French */
  'HU' => "magyar nyelv",  /* Hungarian */
  'ID' => "Bahasa Indonesia",  /* Indonesian */
  'IT' => "italiano",  /* Italian */
  'JA' => "日本語",  /* Japanese */
  'KO' => "한국어",  /* Korean */
  'LT' => "lietuvių kalba",  /* Lithuanian */
  'LV' => "latviešu",  /* Latvian */
  'NB' => "norsk bokmål",  /* Norwegian (Bokmål) */
  'NL' => "Dutch",  /* Dutch */
  'PL' => "polski",  /* Polish */
  'PT' => "português",  /* Portuguese */  /* unspecified variant for backward compatibility; please select PT-BR or PT-PT instead */
  'PT-BR' => "português",  /* Portuguese (Brazilian) */
  'PT-PT' => "português",  /* Portuguese (all Portuguese varieties excluding Brazilian Portuguese) */
  'RO' => "limba română",  /* Romanian */
  'RU' => "русский язык",  /* Russian */
  'SK' => "slovenčina",  /* Slovak */
  'SL' => "slovenski jezik",  /* Slovenian */
  'SV' => "Svenska",  /* Swedish */
  'TR' => "Türkçe",  /* Turkish */
  'UK' => "українська мова",  /* Ukrainian */
  'ZH' => "中文"  /* Chinese (simplified) */
  ];



private static function getCallParams () {
  global $DEEPL_API_KEY;
 $host = "api-free.deepl.com";   // OPTIONS:    api-free.deepl.com   or    api.deepl.com

  $callParams = [
    'http' => [
      'method' => "POST",
      'header' => [
        "Host: $host",
        "Authorization: DeepL-Auth-Key $DEEPL_API_KEY",
        "User-Agent: " . " DanteWiki",
        "Content-Type: application/json"
      ],
    'timeout' => 10.0
    ]
  ];
  return $callParams;

}


private static function getUsage () {}

private static function getLanguages () {}



  /**
   * @param string $text
   * string $tolang
   * return string
   *  ""  failed
   */
private static function callDeepL( $text, $tolang ) {
  global $DEEPL_API_KEY; 

  if ( empty( $text ) )    { danteLog ("DantePresentations", "SubTranslate: empty text, not sending to deepl \n");            return "";}
  if ( empty( $tolang ) )  { danteLog ("DantePresentations", "SubTranslate: empty target language, not sending to deepl \n"); return ""; }

  $tolang = strtoupper( $tolang );
  $host   = "api-free.deepl.com";   // OPTIONS:    api-free.deepl.com   or    api.deepl.com

  /* make parameter to call API */
  $data = [
    'target_lang'  => $tolang,
    'tag_handling' => "html",
    'text'         => [ $text ]
  ];

  $json = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE );
  if( empty( $json ) ) { /* for debug */  var_dump( json_last_error() );  return ""; }
  if( strlen( $json ) > 131072 ) { danteLog ("DantePresentations", "SubTranslate: encode error or parameter length over 128KiB \n"); return ""; }

  $callParams = self::getCallParams();
  $callParams['http']['content'] = $json;
  array_push ( $callParams['http']['header'], "Content-Length: " . strlen ( $json ) );
  $stream = stream_context_create( $callParams );

  /* https://www.deepl.com/ja/docs-api/translate-text/multiple-sentences */

  $ret = file_get_contents( "https://$host/v2/translate", false, $stream );

  if( empty( $ret ) ) {  danteLog ("DantePresentations", "SubTranslate: deepl returned empty \n"); return ""; }
  danteLog ("DantePresentations", "SubTranslate: deepl returned: --------------------------- \n " . print_r($ret, true) . " \n\n------------------------------\n");
  $json = json_decode( $ret, true );

  return $json['translations'][0]['text'] ?? "";
}


  /**
   * store cache data in MediaWiki ObjectCache mechanism
   * https://www.mediawiki.org/wiki/Object_cache
   * https://doc.wikimedia.org/mediawiki-core/master/php/classObjectCache.html
   * https://doc.wikimedia.org/mediawiki-core/master/php/classBagOStuff.html
   *
   * @param string $key
   * @param string $value
   * @param string $exptime Either an interval in seconds or a unix timestamp for expiry
   * @return bool Success
   */
private static function storeCache( $key, $value, $exptime = 0 ) {
  global $wgSubTranslateCaching, $wgSubTranslateCachingTime;
  if( empty( $wgSubTranslateCaching ) ) {return false;}

  /* Cache expiry time in seconds, default = 86400sec (1d) */
  if( !$exptime ) { $exptime = $wgSubTranslateCachingTime ?? 86400; }

    $cache = ObjectCache::getInstance( CACHE_ANYTHING );
    $cachekey = $cache->makeKey( 'subtranslate', $key );
    return $cache->set( $cachekey, $value, $exptime );
}


  /**
   * get cached data from MediaWiki ObjectCache mechanism
   * https://www.mediawiki.org/wiki/Object_cache
   * https://doc.wikimedia.org/mediawiki-core/master/php/classObjectCache.html
   * https://doc.wikimedia.org/mediawiki-core/master/php/classBagOStuff.html
   *
   * @param string $key
   * @return mixed
   */
private static function getCache( $key ) {
    global $wgSubTranslateCaching, $wgSubTranslateCachingTime;
    if( empty( $wgSubTranslateCaching ) ) { return null; }

    $cache = ObjectCache::getInstance( CACHE_ANYTHING );
    $cachekey = $cache->makeKey( 'subtranslate', $key );
    if( $wgSubTranslateCachingTime === false ) { $cache->delete( $cachekey ); return null; }
    return $cache->get( $cachekey );
}





private static function getSubstringAfterSeparator( $inputString, $sep ) { 
  $lastSlashPos = strrpos( $inputString, $sep );   // Find the suffix after the computer emoji (folloed by language code for machine translation)
  if ( $lastSlashPos !== false ) {return substr( $inputString, $lastSlashPos + 1 );}    // If a slash is found, return the substring after it
  return $inputString;                                                                  // If no slash is found, return the original string
}



public static function onExtensionLoadSetup() { global $wgNamespacesWithSubpages; $wgNamespacesWithSubpages[2200] = true;}










///////// REPLICA of Title::getsubpages here !
public static function getSubpages( $title, $limit = -1 ) {
  if (!MediaWikiServices::getInstance()->getNamespaceInfo()->hasSubpages( $title->getNamespace() )) {

     danteLog ("DantePresentations", "Title: ".$title."\n");
     danteLog ("DantePresentations", "Title namespace ".$title->getNamespace()."\n");


     danteLog ("DantePresentations", "  This namespace allows no subpages -\n");

			return [];
		}

		$options = [];
		if ( $limit > -1 ) {
			$options['LIMIT'] = $limit;
		}

		$pageStore = MediaWikiServices::getInstance()->getPageStore();
		$query = $pageStore->newSelectQueryBuilder()
			->fields( $pageStore->getSelectFields() )
			->whereTitlePrefix( $title->getNamespace(), $title->getDBkey() . '/' )
			->options( $options )
			->caller( __METHOD__ );

		return TitleArray::newFromResult( $query->fetchResultSet() );
	}






















// this is my DANTE version !
public static function onArticleViewHeader ( &$article, &$outputDone, bool &$pcache ) {
  global $wgOut;
  $title = $article->getTitle();    // Get the title of the current page

  danteLog ("DantePresentations", "onArticleViewHeader found page:  " .$title." \n");

  $titleSubs = $title->getSubpages();
  $titleSubsNum = count ($titleSubs);
  danteLog ("DantePresentations", "title subs:" . $titleSubsNum . "\n");


  $translateNamespaceId = 2200;                                                      // NS_TRANSLATE 
  $translateTitle = Title::makeTitle( $translateNamespaceId, $title->getText() );    // Check if a page with the same title exists in the Translate namespace

  danteLog ("DantePresentations", "onArticleViewHeader found translate title:  " .$translateTitle." \n");

  if ( ! $translateTitle->exists() ) { return true; }       // if not found, continue with normal processing
  
 danteLog ("DantePresentations", "onArticleViewHeader : translate title exists! \n");

  $hasSubpages = $translateTitle->hasSubpages(); danteLog ("DantePresentations", "oARTICLEVIEWHEADER: hasSubpages: " . print_r ($hasSubpages, true) . "\n");

  // we DO have a matching page in the Translated namespace, which means that at least some machine translation variant or human translation variant or AI production exists
/*
    $wgOut->redirect( $translateTitle->getFullURL() );    // issue a redirect             
    $outputDone = true;                                   // To stop the current page from rendering
    return false;
*/

  $subpages =   self::getSubpages ( $translateTitle ); // $translateTitle->getSubpages();  // get all subpages of the matching page
  $num = count ($subpages);

  danteLog ("DantePresentations", "onArticleViewHeader found $num subpages of $translateTitle\n");
  foreach ( $subpages as $subpage ) {
    $subText   = $subpage->getFullText();
    $subTitle  = $subpage->getTitle();
    $subSuffix = self::getSubstringAfterSeparator ( $subTitle, '💻');
    danteLog ("DantePresentations", " subpage found. Title=" . $subTitle  . " and machine translation suffix= " .$subSuffix ."\n");

  }



  return true;  // continue with normal page rendering
} // onArticleViewHeader








  /**
   * https://www.mediawiki.org/wiki/Manual:Hooks/ArticleViewHeader
   * @param Article &$article
   *  https://www.mediawiki.org/wiki/Manual:Article.php
   *  bool or ParserOutput &$outputDone
   *  bool &$pcache
   * return null
   */
public static function onArticleViewHeaderSUBTRANSLATE( &$article, &$outputDone, bool &$pcache ) {
  global $wgContentNamespaces, $wgSubTranslateSuppressLanguageCaption, $wgSubTranslateRobotPolicy;

  danteLog ("DantePresentations", "onArticleViewHeader \n");
  $pcache = true;  // use parser cache

  $out = $article->getContext()->getOutput();

  if( $article->getPage()->exists() ) { 

//    $out->addHTML( "<div class='hello-world-notice'>Hello Worldmmmmmmmmmm</div>" );
    danteLog ("DantePresentations", "SubTranslate: page exists \n"); 

    return;
  }



    /* check namespace */
    $title = $article->getTitle();
    $ns = $title->getNamespace();
    if( empty( $wgContentNamespaces ) ) {
      if( $ns != NS_MAIN ) { danteLog ("DantePresentations", "SubTranslate: non main namespace \n"); return; }
    } 
    elseif ( !in_array( $ns, $wgContentNamespaces, true ) ) { danteLog ("DantePresentations", "SubTranslate: not content namespace \n");  return;}

    $fullpage = $title->getFullText();
    $basepage = $title->getBaseText();
    $subpage  = $title->getSubpageText();

    if( strcmp( $basepage, $subpage ) === 0 ) { danteLog ("DantePresentations", "SubTranslate:  This is not a subpage situation since: fullpage=$fullpage   basepage=$basepage  subpage=$subpage \n"); return;}

    if( !preg_match('/^[A-Za-z][A-Za-z](\-[A-Za-z][A-Za-z])?$/', $subpage ) ) { danteLog ("DantePresentations", "SubTranslate: The subpage ($subpage) does not denote a language code \n"); return; }
    if( !array_key_exists( strtoupper( $subpage ), self::$targetLangs ) ) { danteLog ("DantePresentations", "SubTranslate: The subpage code $subpage is not an accepted language code \n");   return; }

    /* create new Title from basepagename */
    danteLog ("DantePresentations", "SubTranslate: making new title $basepage \n");
    $basetitle = Title::newFromText( $basepage, $ns );
    if( $basetitle === null or !$basetitle->exists() ) { danteLog ("DantePresentations", "SubTranslate: failed makign new title \n"); return; }

    /* get title text for replace (basepage title + language caption ) */
    $langcaption = ucfirst( MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageName( $subpage ) );
    $langcaptionN = self::$targetLangs[ strtoupper( $subpage ) ] ;
    $langtitle = $wgSubTranslateSuppressLanguageCaption ? "" : $basetitle->getTitleValue()->getText() . '<span class="targetlang"> (' . $langcaption . ', ' .$langcaptionN. ', machine translation)</span>';
    danteLog ("DantePresentations", "SubTranslate: language caption: $langcaption  lang title $langtitle \n");


    /* create WikiPage of basepage */
    $page = WikiPage::factory( $basetitle );
    if( $page === null or !$page->exists() ) { danteLog ("DantePresentations", "SubTranslate: could not make wiki page of base \n");return; }

    $out = $article->getContext()->getOutput();

    $cachekey = $basetitle->getArticleID() . '-' . $basetitle->getLatestRevID() . '-' . strtoupper( $subpage );
    danteLog ("DantePresentations", "SubTranslate: cachekey is: $cachekey \n");
    $text = self::getCache( $cachekey );

    /* translate if cache not found */
    if( true ||  empty( $text ) ) {

      danteLog ("DantePresentations", "SubTranslate: cache failure on cachekey $cachekey \n");

      $content = $page->getContent();
      $text    = ContentHandler::getContentText( $content );
      danteLog ("DantePresentations", "SubTranslate: content of base page is as follows: ---------------------------------------------- \n");
      danteLog ("DantePresentations", $text . " \n\n ----------------------------------------------------------\n");

      $page->clear();
      unset($page);
      unset($basetitle);

      $text = self::callDeepL( $out->parseAsContent( $text ), $subpage );
      if( empty( $text ) ) { danteLog ("DantePresentations", "SubTranslate: DEEPL returned empty \n"); return; }
      else  {  danteLog ("DantePresentations", "SubTranslate: translation is as follows: --------------------------------- \n $text \n\n------------------------------------\n"); }

      /* store cache if enabled */
       self::storeCache( $cachekey, $text );
    }
    else { danteLog ("DantePresentations", "SubTranslate: cache hit on cachekey $cachekey \n"); }

    $out->clearHTML();
    $out->addHTML( $text );

    if( $langtitle ) { $out->setPageTitle( $langtitle ); }

    /* set robot policy */
    if( !empty( $wgSubTranslateRobotPolicy ) ) {
      /* https://www.mediawiki.org/wiki/Manual:Noindex */
      $out->setRobotpolicy( $wgSubTranslateRobotPolicy );
    }

  /* stop to render default message */
  $outputDone = true;

  return;
}
}
