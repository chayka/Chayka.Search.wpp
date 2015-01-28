<?php

namespace Chayka\Search;

use Chayka\Helpers\DateHelper;
use Chayka\Helpers\Util;
use Chayka\WP\Models\PostModel;

class SearchHelper {

    const META_FIELD_INDEXED = 'last_indexed';
    const SITE_OPTION_SEARCH_ENABLED = 'SearchEnabled';

    protected static $totalFound;
    protected static $results;
    protected static $searchEnabled;
    protected static $indexerEnabled = true;
    protected static $scopes;
    protected static $isMultiIndex = false;

    /**
     * Get index
     * @return \Zend_Search_Lucene
     * @internal param string $indexId
     */
    public static function getIndex(){
        return LuceneHelper::getInstance();
    }

    /**
     * Gets (loads from WP options if needed) list of search enabled post types.
     * This option is set from admin page.
     *
     * @return array
     */
    public static function getSearchEnabledPostTypes(){
        if(!isset(self::$searchEnabled)){
            $value = OptionHelper::getOption(self::SITE_OPTION_SEARCH_ENABLED, '');
            self::$searchEnabled = $value?explode(',', $value):array();
        }
        return self::$searchEnabled;
    }

    /**
     * Sets (and saves to WP options) list of search enabled post types.
     *
     * @param $postTypes
     */
    public static function setSearchEnabledPostTypes($postTypes){
        self::$searchEnabled = array_unique($postTypes);
        self::saveSearchEnabledPostTypes();
    }

    /**
     * Saves current set of search enabled post types
     *
     * @return string
     */
    public static function saveSearchEnabledPostTypes(){
        $enabled = self::getSearchEnabledPostTypes();
        $value = join(',', $enabled);
        OptionHelper::setOption(self::SITE_OPTION_SEARCH_ENABLED, $value);
        return $value;
    }

    /**
     * Enable post type for search (updates WP option)
     *
     * @param string $postType
     * @return bool
     */
    public static function enableSearch($postType){
        if(!$postType){
            return false;
        }
        $enabled = self::getSearchEnabledPostTypes();
        if(is_array($postType)){
            self::$searchEnabled = array_unique(array_merge($enabled, $postType));
            self::saveSearchEnabledPostTypes();
        }else{
            if(!in_array($postType, $enabled)){
                self::$searchEnabled[]=$postType;
                self::saveSearchEnabledPostTypes();
            }
        }
        return true;
    }

    /**
     * Disable post type for search (updates WP option)
     *
     * @param string $postType
     * @return bool
     */
    public static function disableSearch($postType){
        if(!$postType){
            return false;
        }
        $enabled = self::getSearchEnabledPostTypes();
        if(!is_array($postType)){
            $postType = array($postType);
        }
        foreach($enabled as $i=>$value){
            if(in_array($value, $postType)){
                unset(self::$searchEnabled[$i]);
            }
        }
        self::$searchEnabled = array_values(self::$searchEnabled);
        self::saveSearchEnabledPostTypes();
        return true;
    }

    /**
     * Checks whether post type is search enabled
     *
     * @param $postType
     * @return bool
     */
    public static function isSearchEnabled($postType){
        $enabled = self::getSearchEnabledPostTypes();
        return in_array($postType, $enabled);
    }

    /**
     * Turn indexer on
     */
    public static function enableIndexer(){
        self::$indexerEnabled = true;
    }

    /**
     * Turn indexer off
     */
    public static function disableIndexer(){
        self::$indexerEnabled = false;
    }

    /**
     * Check if indexer is turned on
     *
     * @return bool
     */
    public static function isIndexerEnabled(){
        return self::$indexerEnabled;
    }

    /**
     * Get set up scope options.
     * Loads form WP option if necessary.
     * Scope can be just a post_type or an alias to combined set of post types
     *
     * Sample:
     * all;Everywhere
     * post;Articles
     * catalog;My catalog;catalog_car, catalog_bike
     *
     * @return mixed
     */
    public static function getScopes(){
        if(!self::$scopes){
            $raw = OptionHelper::getOption('areas');
            $rawStrings = preg_split('%\r?\n%', $raw);
            foreach ($rawStrings as $string) {
                $raws = preg_split('%\s*;\s*%', $string);

                $scope = Util::getItem($raws, 0);
                $label = Util::getItem($raws, 1);
                $postTypes  = Util::getItem($raws, 2);
                self::$scopes[$scope]=array('label'=>$label);
                if($postTypes){
                    $postTypes = preg_split('%\s*,\s*%', $postTypes);
                    self::$scopes[$scope]['postTypes'] = array();
                    foreach($postTypes as $postType){
                        if(get_post_type_object($postType) && self::isSearchEnabled($postType)){
                            self::$scopes[$scope]['postTypes'][] = $postType;
                        }
                    }
                    if(!count(self::$scopes[$scope]['postTypes'])){
                        unset(self::$scopes[$scope]);
                    }
                }elseif($scope!='all' && (!get_post_type_object($scope) || !self::isSearchEnabled($scope))){
                    unset(self::$scopes[$scope]);
                }
            }
        }

        return self::$scopes;
    }

    /**
     * Resolves post_types by scope_name
     *
     * @param string $scope
     * @return array
     */
    public static function resolvePostTypes($scope){
        if(!$scope || 'all' == $scope){
            return null;
        }

        $scopes = self::getScopes();

        $area = Util::getItem($scopes, $scope);

        if(!$area){
            return null;
        }

        $postTypes = Util::getItem($area, 'postTypes', null);

        return $postTypes?$postTypes:array($scope);
    }

    /**
     * Load search query samples setup in admin area.
     *
     * @return array|null
     */
    public static function getQuerySamples(){
        $samples = OptionHelper::getOption('samples');
        $samples = explode("\n", $samples);
        return $samples;
    }

    /**
     * Get one random search query sample setup in admin area.
     *
     * @return string
     */
    public static function getQuerySampleRandom(){
        $samples = self::getQuerySamples();
        return $samples && count($samples)?$samples[array_rand($samples)]:'';
    }

    /**
     * Get Search page WP template setup in admin area
     *
     * @return null|string
     */
    public static function getPageTemplate(){
        $t = OptionHelper::getOption('template');
        return $t?get_stylesheet_directory().'/'.$t:null;
    }

    /**
     * Set search results limit. 0 - unlimited.
     *
     * @param int $limit
     */
    public static function setLimit($limit = 0){
        LuceneHelper::setLimit($limit);
    }

    /**
     * Set the only field for the search. Search is performed by all fields if not set.
     * Handy for quick search by titles.
     *
     * @param string|null $field
     */
    public static function setDefaultSearchField($field = null){
        LuceneHelper::setDefaultSearchField($field);
    }

    /**
     * Search posts
     *
     * @param $searchQuery
     * @param $scope
     * @param int $page
     * @param int $itemsPerPage
     * @param null $searchField
     * @param bool $shuffle
     * @return array
     */
    public static function searchPosts($searchQuery, $scope, $page = 1, $itemsPerPage = 5, $searchField = null, $shuffle = false){
        $postTypes = self::resolvePostTypes($scope);
        if($postTypes){
            $postTypes = array_intersect(self::getSearchEnabledPostTypes(), $postTypes);
        }else{
            $postTypes = self::getSearchEnabledPostTypes();
        }
        if(!count($postTypes)){
            return array();
        }

        self::getIndex();

        $reorderedQuery = LuceneHelper::reorderWordsInQuery($searchQuery, $searchField?array($searchField):null);

        $strQuery = '('.$reorderedQuery.')';

        if('vip_keywords' == $searchField){
            $strQuery .= ' AND (vip_search_status: VS_YES)';
        }

        self::setDefaultSearchField($searchField);

        $luceneQuery = LuceneHelper::parseQuery($strQuery);
        $hits = LuceneHelper::searchHits($luceneQuery);

        LuceneHelper::setQuery($searchQuery);

        self::setDefaultSearchField(null);

        if(empty($hits)){
            return array();
        }

        $posts = array();
        if(count($hits)){
            $ids = array();
//            $scores = array();
            foreach($hits as $hit){
                try{
                    $id = substr($hit->getDocument()->getFieldValue(LuceneHelper::getIdField()), 3);
                    $ids[]=$id;
                }catch(\Exception $e){
                    die($e->getMessage());
                }
//                $scores[$id]=$hit->score;
            }

            self::$totalFound = count($ids);
//            printf('[Q: %s, S: %s, f: %d] ', $term, $scope, self::$totalFound);
            if($shuffle){
                shuffle($ids);
            }

            $ids = array_slice($ids, ($page - 1)*$itemsPerPage, $itemsPerPage);

            $posts = PostModel::query()
                ->postType($postTypes)
                ->postIdIn($ids)
                ->postsPerPage($itemsPerPage)
                ->postStatus_Publish()
                ->orderBy_None()
                ->select();
            $tmp = array();
            foreach($posts as $post){
                $tmp[$post->getId()] = $post;
            }
            $posts = $tmp;
            $tmp = array();
            foreach($ids as $id){
                $tmp[$id] = $posts[$id];
//                $tmp[$id]->getWpPost()->score = $scores[$id];
            }
            $posts = $tmp;
        }
        LuceneHelper::getInstance()->resetTermsStream();

        return $posts;
    }

    /**
     * Get total number of found documents
     *
     * @return int
     */
    public static function getTotalFound(){
        return self::$totalFound;
    }

    /**
     * Highlight found words in $html fragment
     *
     * @param $html
     * @param string $query
     * @return mixed
     */
    public static function highlight($html, $query=''){
        return OptionHelper::getOption('highlight')?
            LuceneHelper::highlight($html, $query):
            $html;
    }

    /**
     * Get number of documents in index.
     * Specify $postType to get number of custom post type documents
     *
     * @param string $postType
     * @return int
     */
    public static function postsInIndex($postType = ''){
//        self::getIndex();
        if($postType){
//            Util::print_r($postType);
//            return LuceneHelper::docFreq($postType, 'post_type');
//            $lquery = LuceneHelper::parseQuery(
//                sprintf('post_type: %s', $postType)
//                sprintf('%s', $postType)
//            );
//            $hits = LuceneHelper::searchHits($lquery);
            self::commit();
            LuceneHelper::setDefaultSearchField('post_type');
            $hits = LuceneHelper::searchHits($postType);
            return count($hits);
        }

        return self::getIndex()->numDocs();
    }

    /**
     * Packs post to intermediate Lucene Doc hash structure, each hash value is in format
     * $item[field] = array(lucene_field_type, value, weight)
     *
     * This function uses filters
     *   $item = apply_filters('pack_lucene_post', $item, $post->getWpPost());
     *   $item = apply_filters('pack_lucene_post_model', $item, $post);
     *
     * @param \WP_Post|PostModel $post
     * @return array
     */
    public static function packPostToLuceneDoc($post){
        if(!$post){
            return null;
        }
        if(!($post instanceof PostModel)){
            $post = PostModel::unpackDbRecord($post);
        }
        $item[LuceneHelper::getIdField()] = array('keyword', 'pk_'.$post->getId());
        $item['post_type'] = array('keyword', $post->getType());
        $item['title'] = array('unstored', $post->getTitle(), 2);
        $content = apply_filters('the_content', $post->getContent());
        $item['content'] = array('unstored', wp_strip_all_tags($content));
        $item['user_id'] = array('keyword', 'user_'.$post->getUserId());
        $taxonomies = get_taxonomies();
        foreach ($taxonomies as $taxonomy){
            $post->loadTerms($taxonomy);
        }
        $t = $post->getTerms();
        foreach($t as $taxonomy=>$terms){
            if(count($terms)){
                $item[$taxonomy] = array('unstored', join(', ', $terms));
            }
        }

        $item = apply_filters('pack_lucene_post', $item, $post->getWpPost());

        $item = apply_filters('pack_lucene_post_model', $item, $post);

        $vipKeywords = trim($post->getMeta('vip_keywords'));

        if($vipKeywords){
            $item['vip_keywords'] = array('unstored', $vipKeywords, 0.001);
            $item['vip_search_status'] = array('keyword', 'VS_YES');
        }else{
//            $item['vip_search_status'] = array('keyword', 'VS_NO');
        }

        return $item;
    }

    /**
     * Index post
     *
     * @param $post
     * @return null
     */
    public static function indexPost($post){
        if(!$post){
            return null;
        }
        if(!($post instanceof PostModel)){
            $post = PostModel::unpackDbRecord($post);
        }

        $item = self::packPostToLuceneDoc($post);
//        Util::print_r($item);
        self::getIndex();
        $doc = LuceneHelper::luceneDocFromArray($item);
        LuceneHelper::indexLuceneDoc($doc);

        $post->updateMeta(self::META_FIELD_INDEXED, DateHelper::datetimeToDbStr(new \DateTime()));

        return null;
    }

    /**
     * Delete post from index (not from WP)
     *
     * @param $postId
     * @param string $suffix
     * @return int
     */
    public static function deletePost($postId, $suffix = ''){
        delete_post_meta($postId, self::META_FIELD_INDEXED);
        self::getIndex($suffix);
        return LuceneHelper::deleteById('pk_'.$postId);
    }

    /**
     * Delete posts form index (not from WP) by some key
     *
     * @param $key
     * @param $value
     * @return int
     */
    public static function deletePostsByKey($key, $value){
        global $wpdb;
        self::getIndex();
        $sql = $wpdb->prepare(
            "DELETE pm
            FROM $wpdb->postmeta AS pm
            LEFT JOIN $wpdb->posts AS p ON(pm.post_id = p.ID)
            WHERE pm.meta_key = %s AND p.$key = %s",
            self::META_FIELD_INDEXED, $value);

        $wpdb->query($sql);
        return LuceneHelper::deleteByKey($key, $value);
    }

    /**
     * Flush index
     *
     * @return int
     */
    public static function flush(){
        global $wpdb;
        $sql = $wpdb->prepare(
            "DELETE pm
            FROM $wpdb->postmeta AS pm
            WHERE pm.meta_key = %s",
            self::META_FIELD_INDEXED);
        $wpdb->query($sql);
        return LuceneHelper::flush();
    }

    /**
     * Commit index changes
     * @param string $indexId
     */
    public static function commit(){
        LuceneHelper::getInstance()->commit();
    }

    /**
     * Optimize index
     */
    public static function optimize(){
        $date = new \DateTime();
        OptionHelper::setOption('lastOptimized', DateHelper::datetimeToDbStr($date));
        LuceneHelper::getInstance()->optimize();
    }

    /**
     * Get index info on all post types
     *
     * @param array $postTypes
     * @return array
     */
    public static function getPostTypeInfo($postTypes = array()){
        $allPostTypes = get_post_types(array(

        ), 'objects');
        SearchHelper::getIndex();
        $forbidden = array(
            'attachment',
            'revision',
            'nav_menu_item'
        );
        $postTypeInfo = array();
        foreach($allPostTypes as $name => $postType){
            if(in_array($name, $forbidden)||count($postTypes)&&!in_array($name, $postTypes)){
//                unset($postTypes[$name]);
            }else{
                $postType->total = wp_count_posts($name);
                $postType->indexed = SearchHelper::postsInIndex($name);
                $info = array(
                    'name' => $name,
                    'label' => $postType->label,
                    'total' => (int)$postType->total->publish,
                    'indexed' => (int)$postType->indexed,
                    'enabled' => SearchHelper::isSearchEnabled($name)
                );
                $postTypeInfo[$name]=$info;
            }
        }

        return $postTypeInfo;
    }
}
