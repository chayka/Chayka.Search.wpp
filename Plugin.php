<?php

namespace Chayka\Search;

use Chayka\WP;

class Plugin extends WP\Plugin{

    /* chayka: constants */
    
    public static $instance = null;

    public static function init(){
        if(!static::$instance){
            static::$instance = $app = new self(__FILE__, array(
                /* chayka: init-controllers */
            ));
            $app->dbUpdate(array());
	        $app->addSupport_UriProcessing();
	        $app->addSupport_ConsolePages();
	        $app->addSupport_Metaboxes();
	        $app->addSupport_PostProcessing(100);


            /* chayka: init-addSupport */
        }
    }


    /**
     * Register your action hooks here using $this->addAction();
     */
    public function registerActions() {
        $this->addAction('lucene_index_post', 'indexPost', 10, 2);
        $this->addAction('lucene_delete_post', 'deletePost', 10, 2);
//        $this->addAction('save_post', 'indexPost', 100, 2);
//        $this->addAction('save_post', 'savePost', 90, 2);
//        $this->addAction('delete_post', 'deletePost', 100, 2);
//        $this->addAction('trashed_post', 'deletePost', 100, 2);

        $this->addAction('lucene_enable_indexer', 'enableIndexer', 10);
        $this->addAction('lucene_disable_indexer', 'disableIndexer', 10);
        $this->addAction('parse_request', 'parseRequest');
    	/* chayka: registerActions */
    }

    /**
     * Register your action hooks here using $this->addFilter();
     */
    public function registerFilters() {
		/* chayka: registerFilters */
    }

    /**
     * Register scripts and styles here using $this->registerScript() and $this->registerStyle()
     *
     * @param bool $minimize
     */
    public function registerResources($minimize = false) {
        $this->registerBowerResources(true);

        $this->setResSrcDir('src/');
        $this->setResDistDir('dist/');

		/* chayka: registerResources */
    }

    /**
     * Routes are to be added here via $this->addRoute();
     */
    public function registerRoutes() {
        $this->addRoute('default');
    }

    /**
     * Registering console pages
     */
    public function registerConsolePages(){
//        $this->addConsolePage('Поисковая система', 'update_core', 'chayka-search-admin',
//            '/admin/indexer');
//
//        $this->addConsoleSubPage('chayka-search-admin',
//            'Настройка', 'update_core', 'chayka-search-setup',
//            '/admin/setup-search-engine');
        $this->addConsolePage('Search Engine', 'update_core', 'search-engine', '/admin/search-engine', 'dashicons-search', '80');

        /* chayka: registerConsolePages */
    }
    
    /**
     * Add custom metaboxes here via addMetaBox() calls;
     */
    public function registerMetaBoxes(){
        /* chayka: registerMetaBoxes */
    }

    /**
     * Custom Sidebars are to be added here via $this->registerSidbar();
     */
    public function registerSidebars() {
		/* chayka: registerSidebars */
    }
    
    /* postProcessing */

    /**
     * This is a hook for save_post
     *
     * @param integer $postId
     * @param WP_Post $post
     */
    public function savePost($postId, $post){
        
    }
    
    /**
     * This is a hook for delete_post
     *
     * @param integer $postId
     */
    public function deletePost($postId){
        
    }
    
    /**
     * This is a hook for trashed_post
     *
     * @param integer $postId
     */
    public function trashedPost($postId){
        $this->deletePost($postId);
    }
}