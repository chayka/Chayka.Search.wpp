<?php
/**
 * Created by PhpStorm.
 * User: borismossounov
 * Date: 21.01.15
 * Time: 20:07
 */

namespace Chayka\Search;


use Chayka\Helpers\FsHelper;
use Chayka\Helpers\Util;
use phpMorphy;
use ZendSearch\Lucene;

class LuceneHelper {

    /**
     * @var array
     */
    protected static $instance = array();

    /**
     * @var MorphyFilter
     */
    protected static $morphy;

    /**
     * @var string
     */
    protected static $idField = "PK";

    /**
     * @var array
     */
    protected static $queries;

    /**
     * @var Lucene\Search\Query\AbstractQuery
     */
    protected static $query;

    /**
     * @var Lucene\Search\QueryLexer
     */
    protected static $lexer;

    /**
     * Get index directory
     *
     * @param string $indexId
     * @return string
     */
    public static function getDir($indexId = ''){
        $basePath = '';
        switch(OptionHelper::getOption('indexLocation')){
            case 'child':
                $basePath = get_stylesheet_directory().'/';
                break;
            case 'parent':
                $basePath = get_template_directory().'/';
                break;
            case 'plugin':
            default:
                $basePath = Plugin::getInstance()->getBasePath();
        }
        $indexFnDir =  $basePath . 'data/lucene/' . Util::serverName();
        if($indexId){
            $indexFnDir.='.'.$indexId;
        }
        return $indexFnDir;
    }

    /**
     * Get Lucene instance
     * @param string $indexId
     * @return Lucene\Index
     */
    public static function getInstance($indexId = '') {
        if (empty(self::$instance[$indexId])) {
            self::initAnalyzer();
            $indexFnDir = self::getDir($indexId);
            self::$instance[$indexId] = new Lucene\Index($indexFnDir, !is_dir($indexFnDir));
        }

        return self::$instance[$indexId];
    }

    /**
     * Analyzer Initialization
     */
    public static function initAnalyzer(){
        $analyzer = Lucene\Analysis\Analyzer\Analyzer::getDefault();

        if(!($analyzer instanceof Analyzer)){

            try {
                Lucene\Search\QueryParser::setDefaultEncoding('utf-8');
                $analyzer = new Analyzer();
                $analyzer->addFilter(self::getMorphy());
                Lucene\Analysis\Analyzer\Analyzer::setDefault($analyzer);

            } catch (\Exception $e) {
                die('Exception: ' . $e->getMessage());
            }
        }

    }

    /**
     * Get instance of MorphyFilter.
     * Since it is heavy, has cache and has to load dictionaries, makes sense to keep it singleton.
     *
     * @return MorphyFilter
     */
    public static function getMorphy(){
        if(!self::$morphy){
            self::$morphy = new MorphyFilter();
        }
        return self::$morphy;
    }

    /**
     * Remove existing index.
     * Physically removes index folder.
     *
     * @param string $indexId
     * @return int
     */
    public static function flush($indexId = ''){
        $dir = self::getDir($indexId);
        if(isset(self::$instance[$indexId])){
            unset(self::$instance[$indexId]);
        }
        return FsHelper::delete($dir);
    }

    /**
     * Set primary key field
     *
     * @param $value
     */
    public static function setIdField($value) {
        self::$idField = $value;
    }

    /**
     * Get Primary key field
     *
     * @return string
     */
    public static function getIdField() {
        return self::$idField;
    }

    /**
     * Set search results limit. 0 - unlimited.
     *
     * @param int $limit
     */
    public static function setLimit($limit = 0){
        Lucene\Lucene::setResultSetLimit($limit);
    }

    /**
     * Set default search field
     *
     * @param string $field
     */
    public static function setDefaultSearchField($field){
        Lucene\Lucene::setDefaultSearchField($field);
    }

    /**
     * Set search query, parse if needed.
     *
     * @param string|Lucene\Search\Query\AbstractQuery $query
     */
    public static function setQuery($query) {
        if ($query) {
            if ($query instanceof Lucene\Search\Query\AbstractQuery) {
                self::$query = $query;
            } elseif (is_string($query)) {
                self::$query = Util::getItem(self::$queries, $query);
                if (!self::$query) {
                    self::$query = self::parseQuery($query);
                }
            }
        }
    }

    /**
     * Get current query object.
     *
     * @return Lucene\Search\Query\AbstractQuery
     */
    public static function getQuery(){
        return self::$query;
    }

    /**
     * Parse string query, store parsed query in internal hash
     *
     * @param string $query
     * @return Lucene\Search\Query\AbstractQuery
     */
    public static function parseQuery($query) {
        if (empty(self::$queries[$query])) {
            self::$queries[$query] = Lucene\Search\QueryParser::parse($query, 'utf-8');
        }

        return self::$queries[$query];
    }

    /**
     * Get lexer
     *
     * @return Lucene\Search\QueryLexer
     */
    public static function getLexer(){
        if(!self::$lexer){
            self::$lexer = new Lucene\Search\QueryLexer();
        }
        return self::$lexer;
    }

    /**
     * Tokenize string
     *
     * @param string $str
     * @param string $encoding
     * @return array(Zend_Search_Lucene_Search_QueryToken);
     */
    public static function tokenize($str, $encoding = 'utf-8'){
        $lexer = self::getLexer();
        return $lexer->tokenize($str, $encoding);
    }

    /**
     * Reorders words in the query to search more unique words at first
     *
     * @param string $query
     * @param array|null $searchFields
     * @param string $indexId
     * @return string Description
     */
    public static function reorderWordsInQuery($query, $searchFields = null, $indexId = ''){
        $tokens = self::tokenize($query);
        $lucene = self::getInstance($indexId);
        $morphy = self::getMorphy();
        $fieldNames = $searchFields?
            $searchFields:
            $lucene->getFieldNames(true);
        foreach($tokens as $token){
            $normalized = $morphy->normalizeWord($token->text);
            $numDocs = 0;
            foreach($fieldNames as $fieldName){
                $term = new Lucene\Index\Term($normalized, $fieldName);
                $numDocs += $lucene->docFreq($term);
            }
            $token->numDocs = $numDocs;
        }

        usort($tokens, function ($a, $b){
            $diff = $a->numDocs - $b->numDocs;
            return $diff ? $diff : $a->position - $b->position;
        });

        $words = array();
        foreach($tokens as $token){
            $words[]=$token->text;
        }

        return implode(' ', $words);
    }

    /**
     * Extract query from http referrer.
     * Performs preliminary search.
     * Might be handy when you need to highlight search query for the user that came from search results page.
     *
     * @param int $postId
     * @return mixed|string
     */
    public static function getQueryFromHttpReferer($postId = 0) {
        $url = Util::getItem($_SERVER, 'HTTP_REFERER');
        if (strpos($url, "search")) {
            $urlQuery = parse_url($url, PHP_URL_QUERY);
            $params = array();
            parse_str($urlQuery, $params);
            $query = Util::getItem($params, 'q');
            if ($query) {
                $lcQuery = self::parseQuery($postId ?
                    sprintf('%s: pk_%d AND (%s)', self::$idField, $postId, $query) :
                    $query);
                self::setQuery($lcQuery);
                self::searchIds($lcQuery);
            }

            return $query;
        }

        return '';
    }

    /**
     * Convert array post data (prepped by packLuceneDoc) to Lucene Document
     *
     * @param array $item
     * @return \Zend_Search_Lucene_Document
     */
    public static function luceneDocFromArray($item) {
        $doc = new Lucene\Document();
        foreach ($item as $field => $opts) {
            $encoding = 'utf-8';
            $type = 'unstored';
            $value = $opts;
            $boost = 1;
            if (is_array($opts)) {
                switch (count($opts)) {
                    case 2:
                        list($type, $value) = $opts;
                        $boost = 1;
                        break;
                    case 3:
                        list($type, $value, $boost) = $opts;
                        break;
                }
            }

            if ('keyword' == $type) {
                $doc->addField(Lucene\Document\Field::keyword($field, $value, $encoding));
            } elseif ('unindexed' == $type) {
                $doc->addField(Lucene\Document\Field::unIndexed($field, $value, $encoding));
            } elseif ('binary' == $type) {
                $doc->addField(Lucene\Document\Field::binary($field, $value));
            } elseif ('text' == $type) {
                $doc->addField(Lucene\Document\Field::text($field, $value, $encoding));
            } elseif ('unstored' == $type) {
                $doc->addField(Lucene\Document\Field::unStored($field, $value, $encoding));
            }
            $doc->getField($field)->boost = $boost;
        }

        return $doc;
    }

    /**
     * Delete lucene documents from index by key
     *
     * @param $key
     * @param $value
     * @param string $indexId
     * @return int
     * @throws \Zend_Search_Lucene_Exception
     */
    public static function deleteByKey($key, $value, $indexId = '') {
        $deleted = 0;
        if ($key && $value) {
            $index = self::getInstance($indexId);
            $term = new Lucene\Index\Term($value, $key);
            $docIds = $index->termDocs($term);
            foreach ($docIds as $id) {
                $index->delete($id);
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Delete lucene document by primary key
     *
     * @param $docId
     * @return int
     */
    public static function deleteById($docId) {
        return self::deleteByKey(self::$idField, $docId);
    }

    /**
     * Index Lucene document (put document to index)
     *
     * @param Lucene\Document $doc
     * @param string $indexId
     */
    public static function indexLuceneDoc($doc, $indexId = '') {
        $index = self::getInstance($indexId);
        $id = $doc->getFieldValue(self::$idField);
        try {
            self::deleteById($id);
            $index->addDocument($doc);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * Get number of documents in the index containing $term in the $field
     * Heads Up: This function returns outdated info until index is optimized.
     *
     * @param string $term
     * @param string|null $field
     * @param string $indexId
     * @return int
     */
    public static function docFreq($term, $field = null, $indexId = ''){
        $term = new Lucene\Index\Term($term, $field);
        return self::getInstance($indexId)->docFreq($term);
    }

    /**
     * This function actually searches for term, works longer than docFreq, but more stable
     *
     * @param $term
     * @param null $field
     * @return int
     */
    public static function docNumber($term, $field = null){
        $q = $field? "($field:$term)": $term;
        try {
            $c = count(self::searchHits($q));
            echo "[$q $c]";
            return $c;
        } catch (\Exception $e) {
        }
        return 0;
    }

    /**
     * Search and return lucene hits
     *
     * @param $query
     * @param string $indexId
     * @return array Zend_Search_Lucene_Search_QueryHit
     */
    public static function searchHits($query, $indexId = '') {

        $hits = array();

        try {
            $index = self::getInstance($indexId);
            $hits = @$index->find($query);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        return $hits;
    }

    /**
     * Search and return lucene documents
     *
     * @param $query
     * @return array
     */
    public static function searchLuceneDocs($query) {
        $hits = self::searchHits($query);
        $docs = array();
        foreach ($hits as $hit) {
            $docs[] = $hit->getDocument();
        }
        return $docs;
    }

    /**
     * Search and return ids (primary key values).
     * Handy to fetch docs from db by those ids.
     *
     * @param $query
     * @return array
     */
    public static function searchIds($query) {
        $hits = self::searchHits($query);
        $ids = array();
        foreach ($hits as $hit) {
            $ids[] = $hit->getDocument()->getFieldValue(self::getIdField());
        }
        return $ids;
    }

    /**
     * Highlight found words in $html fragment
     *
     * @param $html
     * @param string $query
     * @return mixed
     */
    public static function highlight($html, $query = '') {
        self::initAnalyzer();
        self::setQuery($query);

        if (self::$query) {
            $html = preg_replace('%(<\/?)b\b%imUs', '$1strong', $html);
        }

        Lucene\Analysis\Analyzer\Analyzer::getDefault()->reset();
        return self::$query ? self::$query->htmlFragmentHighlightMatches($html, 'utf-8') : $html;
    }

}

class Analyzer extends Lucene\Analysis\Analyzer\Common\Utf8\CaseInsensitive{
    /**
     * This is quick fix that enables cyrillic UTF-8 highlighting
     *
     * @param string $data
     * @param string $encoding
     * @return array
     */
    public function tokenize($data, $encoding = 'UTF-8'){
        return parent::tokenize($data, $encoding);
    }

}

class MorphyFilter extends Lucene\Analysis\TokenFilter\LowerCaseUtf8 {

    private $morphy = array();

    protected $cache = array();

    /**
     * Morphy initialization
     */
    public function __construct() {

    }

    /**
     * Get morphy for Russian
     *
     * @return phpMorphy
     */
    public function getMorphyRu(){
        if(empty($this->morphy['ru'])){
            $dir = Plugin::getInstance()->getBasePath() . 'res/morphy/ru';
            $lang = 'ru_RU';

            $this->morphy['ru'] = new phpMorphy($dir, $lang);
        }

        return $this->morphy['ru'];
    }

    /**
     * Get morphy for Russian
     *
     * @return phpMorphy
     */
    public function getMorphyEn(){
        if(empty($this->morphy['en'])){
            $dir = Plugin::getInstance()->getBasePath() . 'res/morphy/en';
            $lang = 'en_EN';

            $this->morphy['en'] = new phpMorphy($dir, $lang);
        }

        return $this->morphy['en'];
    }

    /**
     * Casts given word to the closest normalized form
     *
     * @param phpMorphy $morphy
     * @param string $word
     * @param string $partOfSpeech
     * @param array $variants
     * @return null
     */
    public function castVariants($morphy, $word, $partOfSpeech, $variants) {
        $forms = null;
        foreach ($variants as $v) {
            $forms = $morphy->castFormByGramInfo($word, $partOfSpeech, $v, true);
            if (!empty($forms)) {
                $min = 1000;
                $index = 0;

                foreach ($forms as $i => $form) {
                    $dif = levenshtein($word, $form);
//                    echo "[$word, $form, $dif]\n";
                    if ($dif < $min) {
                        $min = $dif;
                        $index = $i;
                    }
                    if (!$min) {
                        break;
                    }
                }
//                printf("==>%s\n", $forms[$index]);
                return $forms[$index];
            }
        }

        return null;
    }


    /**
     * Detect language
     *
     * @param string $word
     * @return null|string
     */
    public function detectWordLanguage($word){

        $lang = null;

        if(preg_match('%^[А-Яа-я]+$%u', $word)){
            $lang = 'ru';
        }elseif(preg_match('%^[A-Za-z]+$%u', $word)){
            $lang = 'en';
        }

        return $lang;
    }

    /**
     * @param string $word
     * @return null|string
     */
    public function normalizeEnWord($word){
        $morphy = $this->getMorphyEn();

        $omit = false;
        $part = $morphy->getPartOfSpeech($word);
        $form = $word;
        $part = is_array($part) && count($part) ? $part[0] : '';
        switch ($part) {
            case 'NOUN':
                $form = $this->castVariants($morphy, $word, $part, array(
                    array('SG'),
                    array('PL')
                ));
                break;
                break;
            case 'VERB':
                $form = $this->castVariants($morphy, $word, $part, array(
                    array('INF'),
                    array('PRSA', '1', 'SG'),
                ));
                break;
            case 'ADJECTIVE':
            case 'ADVERB':
            case 'NUMERAL':
                break;
            case 'ARTICLE':
            case 'CONJ':
            case 'PREP':
            case 'INT':
            case 'PART':
                $omit = true;
                $form = '---------';
                break;
            default:
        }
        if ($omit) {
            return null;
        }

        return $form?$form:$word;

    }

    /**
     * @param string $word
     * @return null|string
     */
    public function normalizeRuWord($word){
        $morphy = $this->getMorphyRu();

        $omit = false;
        $part = $morphy->getPartOfSpeech($word);
        $form = $word;
        $part = is_array($part) && count($part) ? $part[0] : '';
        switch ($part) {
            case 'С':
            case 'МС':
                $form = $this->castVariants($morphy, $word, $part, array(
                    array('ИМ', 'ЕД'),
                    array('ИМ', 'МН')
                ));
                break;
            case 'П':
            case 'МС-П':
            case 'ЧИСЛ-П':
                $form = $this->castVariants($morphy, $word, $part, array(
                    array('ИМ', 'ЕД', 'МР'),
                ));
                break;
            case 'ПРИЧАСТИЕ':
                $form = $this->castVariants($morphy, $word, $part, array(
                    array('ИМ', 'ЕД', 'МР'),
                ));
                break;
            case 'Г':
            case 'ДЕЕПРИЧАСТИЕ':
            case 'ВВОДН':
                $form = $this->castVariants($morphy, $word, 'ИНФИНИТИВ', array(
                    array('ДСТ', 'СВ', 'НП'),
                    array('ДСТ', 'СВ', 'ПЕ'),
                    array('ДСТ', 'НС', 'НП'),
                    array('ДСТ', 'НС', 'ПЕ'),
                    array('СТР', 'СВ', 'НП'),
                    array('СТР', 'СВ', 'ПЕ'),
                    array('СТР', 'НС', 'НП'),
                    array('СТР', 'НС', 'ПЕ'),
                ));
                break;
            case 'КР_ПРИЛ':
            case 'КР_ПРИЧАСТИЕ':
                $form = $this->castVariants($morphy, $word, $part, array(
                    array('ЕД', 'СР'),
                    array('МН'),
                ));
                break;
            case 'СОЮЗ':
            case 'ПРЕДЛ':
            case 'МЕЖД':
            case 'ЧАСТ':
                $omit = true;
                $form = '---------';
                break;
            case 'ИНФИНИТИВ':
            case 'Н':
            case 'ПРЕДК':
            case 'МС-ПРЕДК':
            case 'ЧИСЛ':
            case 'ФРАЗ':
                break;
            default:
        }
        if ($omit) {
            return null;
        }

        return $form?$form:$word;

    }

    /**
     * Gets normalized form for given word, null for non-indexed words
     *
     * @param $word
     * @return null|string
     */
    public function normalizeWord($word){
        $str = trim(mb_strtoupper($word, "utf-8"));

        $lang = $this->detectWordLanguage($word);

        $cache = Util::getItem($this->cache, $str);

        if($cache){
            return $cache;
        }

        $res = $str;

        switch($lang){
            case 'en':
                $res = $this->normalizeEnWord($str);
                break;
            case 'ru':
                $res = $this->normalizeRuWord($str);
                break;
        }

        $this->cache[$str] = $res;

        return $res;
    }

    /**
     * MorphyFilter function that normalizes given token (word)
     *
     * @param Lucene\Analysis\Token $srcToken
     * @return Lucene\Analysis\Token|null
     */
    public function normalize(Lucene\Analysis\Token $srcToken) {

        $word = $srcToken->getTermText();

        $word = $this->normalizeWord($word);
        if(!$word){
            return null;
        }

        $newToken = new Lucene\Analysis\Token(
            $word,
            $srcToken->getStartOffset(),
            $srcToken->getEndOffset()
        );

        $newToken->setPositionIncrement($srcToken->getPositionIncrement());

        return $newToken;
    }

}