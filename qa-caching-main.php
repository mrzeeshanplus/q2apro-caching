<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
    header('Location: ../../');
    exit;
}

// define('QA_CACHING_DIR', dirname(__FILE__) . '/qa-cache'); //Cache Directory
define('QA_CACHING_DIR', QA_BASE_DIR . 'qa-cache'); //Cache Directory

define('QA_CACHING_STATUS', (int) qa_opt('qa_caching_enabled')); // "1" - Turned On, "0" - Turned off
define('QA_CACHING_EXPIRATION_TIME', (int) qa_opt('qa_caching_expiration_time')); //Cache Expiration In seconds
define('QA_CACHING_COMPRESS', (int) qa_opt('qa_caching_compress')); //Compressed cache
define('QA_CACHING_DEBUG', (int) qa_opt('qa_caching_debug')); //Output debug infomation

class qa_caching_main
{
    protected $is_logged_in, $cache_file, $html, $debug, $timer;
    protected $mobile, $post_method;
    
    /**
     * Function that is called at page initialization, see qa-include/qa-page.php
     */
    function init_page()
    {
        $this->is_logged_in = qa_get_logged_in_userid();
        $this->mobile       = qa_is_mobile_probably();
        $this->post_method  = preg_match("/^(?:POST|PUT)$/i", $_SERVER["REQUEST_METHOD"]);
        $this->timer        = microtime(true);
        
        // get questionid from URL
        $requesturi       = $_SERVER["REQUEST_URI"];
        $requesturi_arr   = explode("/", $requesturi);
        $questionid       = isset($requesturi_arr[1]) ? (int) $requesturi_arr[1] : null;
        $this->questionid = isset($requesturi_arr[1]) ? (int) $requesturi_arr[1] : null;
        
        // cache file name, if $questionid exists than filename is questionid
        $this->cache_file = $this->get_filename($questionid);
        
        if ($this->should_clear_caching()) {
            $this->clear_cache();
        }
        
        // use cache only for questions
        if (!empty($questionid)) {
            if ($this->check_cache() && $this->do_caching()) {
                $this->get_cache();
            } else if ($this->do_caching()) {
                // output buffering on
                ob_start();
            } else {
                return;
            }
        }
        
        return;
    }
    
    /**
     * Function that is called at the end of page rendering, see qa-include/qa-index.php
     * @param type $reason
     * @return type
     */
    function shutdown($reason = false)
    {
        if (!empty($this->questionid)) {
            if ($this->do_caching() && !$this->is_logged_in && !$this->check_cache()) {
                $get_contents = ob_get_contents();
                
                // sometimes ob_get_contents() is empty, memory limit?
                if (empty($get_contents)) {
                    return;
                }
                
                if (QA_CACHING_COMPRESS) {
                    $this->html = $this->compress_html($get_contents);
                } else {
                    $this->html = $get_contents;
                }
                
                if (strpos($this->html, qa_lang_html('main/page_not_found')) !== false) {
                    // if 404 page
                    return;
                }
                
                if (QA_DEBUG_PERFORMANCE) {
                    $endtag = '</html>';
                    $rpos   = strrpos($this->html, $endtag);
                    if ($rpos !== false) {
                        $this->html = substr($this->html, 0, $rpos + strlen($endtag));
                    }
                }
                
                $total_time = number_format(microtime(true) - $this->timer, 4, ".", "");
                $this->debug .= "\n<!-- ++++++++++++CACHED VERSION++++++++++++++++++\n";
                $this->debug .= "Created on " . date('Y-m-d H:i:s') . "\n";
                $this->debug .= "Generated in " . $total_time . " seconds\n";
                $this->debug .= "++++++++++++CACHED VERSION++++++++++++++++++ -->\n";
                $this->write_cache();
            }
        }
        return;
    }
    
    /**
     * Writes file to cache.
     */
    private function write_cache()
    {
        if (!file_exists(QA_CACHING_DIR)) {
            mkdir(QA_CACHING_DIR, 0755, TRUE);
        }
        
        if (is_dir(QA_CACHING_DIR) && is_writable(QA_CACHING_DIR)) {
            if (QA_CACHING_DEBUG) {
                $this->html .= $this->debug;
            }
            
            // semaphores explained: http://www.re-cycledair.com/php-dark-arts-semaphores
            if (function_exists("sem_get") && ($mutex = @sem_get(2013, 1, 0644 | IPC_CREAT, 1)) && @sem_acquire($mutex)) {
                @file_put_contents($this->cache_file, $this->html) . sem_release($mutex);
            } else if (($mutex = @fopen($this->cache_file, "w")) && @flock($mutex, LOCK_EX)) {
                fwrite($mutex, $this->html);
                fflush($mutex);
                flock($mutex, LOCK_UN);
            }
        }
    }
    
    /**
     * Decision to clear cache
     * @return boolean
     */
    private function should_clear_caching()
    {
        if ($this->is_logged_in) {
            if (qa_request_part(0) == 'admin') {
                if ($this->post_method) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Clear cache completely or if $questionid is specified then only selective.
     */
    public function clear_cache($questionid = null)
    {
        if (!empty($questionid)) {
            // clear cache files selectively
            $this->remove_files_selective(QA_CACHING_DIR, $questionid);
        } else {
            // in linux, delete entire directory and rebuild it
            // in windows, just fall through to do the recursive delete
            $comm = "rm -rf " . QA_CACHING_DIR . "; mkdir " . QA_CACHING_DIR;
            @exec($comm); // '@' to ensure no warning, if exec not avail. then it would follow through to recursive delete
            $this->unlinkRecursive(QA_CACHING_DIR);
        }
    }
    
    /**
     * Delete selective cache files according to questionid in cache folder.
     */
    private function remove_files_selective($dir, $questionid = null)
    {
        if (empty($dir) || empty($questionid)) {
            return;
        }
        
        // does the folder exist
        if (!$dh = @opendir($dir)) {
            error_log('FOLDER does not exist: ' . $dir);
            return;
        }
        
        @unlink($dir . '/' . $questionid);
        
        // get all cached files starting with the $questionid_ and delete them
        /*
        foreach(glob($dir.'/'.$questionid."_*.*") as $filename)
        {
        // delete file
        unlink($dir.'/'.$filename);
        }
        */
        
        closedir($dh);
    }
    
    /**
     * Recursively delete files in specific folder.
     */
    private function unlinkRecursive($dir, $deleteFolder = false, $deleteRootToo = false)
    {
        if (!$dh = @opendir($dir)) {
            return;
        }
        while (false !== ($obj = readdir($dh))) {
            if ($obj == '.' || $obj == '..') {
                continue;
            } else if (is_dir($obj) && !$deleteFolder) {
                continue;
            }
            if (!@unlink($dir . '/' . $obj)) {
                $this->unlinkRecursive($dir . '/' . $obj, $deleteFolder, false);
            }
        }
        closedir($dh);
        if ($deleteRootToo) {
            @rmdir($dir);
        }
    }
    
    /**
     * Output cache to the user
     */
    private function get_cache()
    {
        global $qa_usage;
        qa_db_connect('qa_page_db_fail_handler');
        
        qa_page_queue_pending();
        qa_load_state();
        qa_check_login_modules();
        
        qa_check_page_clicks();
        
        $contents = @file_get_contents($this->cache_file);
        
        // cache failure, graceful exit
        if (!$contents) {
            return;
        }
        
        $qa_content = array(); // Dummy contents
        $userid     = qa_get_logged_in_userid();
        $questionid = qa_request_part(0);
        $cookieid   = qa_cookie_get(true);
        
        if (is_numeric($questionid)) {
            $question = qa_db_select_with_pending(qa_db_full_post_selectspec($userid, $questionid));
            if (is_numeric($questionid) && qa_opt('do_count_q_views') && !$this->post_method && !qa_is_http_post() && qa_is_human_probably() && (!$question['views'] || ( // if it has more than zero views
                (($question['lastviewip'] != qa_remote_ip_address()) || (!isset($question['lastviewip']))) // then it must be different IP from last view
                && (($question['createip'] != qa_remote_ip_address()) || (!isset($question['createip']))) // and different IP from the creator
                && (($question['userid'] != $userid) || (!isset($question['userid']))) // and different user from the creator
                && (($question['cookieid'] != $cookieid) || (!isset($question['cookieid']))) // and different cookieid from the creator
                ))) {
                $qa_content['inc_views_postid'] = $questionid;
            } else {
                $qa_content['inc_views_postid'] = null;
            }
            qa_do_content_stats($qa_content);
        }
        
        if (QA_DEBUG_PERFORMANCE) {
            // output buffering on
            ob_start();
            $qa_usage->output();
            $contents .= ob_get_contents();
            ob_end_clean();
        }
        
        qa_db_disconnect();
        
        exit($contents);
    } // END get_cache()
    
    /**
     * Checks if cache exists
     * @return boolean
     */
    private function check_cache()
    {
        if (!file_exists($this->cache_file)) {
            return false;
        }
        if (filemtime($this->cache_file) >= strtotime("-" . QA_CACHING_EXPIRATION_TIME . " seconds")) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Checks if the visitor/user will see the cached version.
     * Only non-registered users see the cached version.
     * @return boolean
     */
    private function do_caching()
    {
        if (empty($this->cache_file)) {
            return false;
        }
        
        if (!QA_CACHING_STATUS) {
            return false;
        }
        
        if ($this->is_logged_in) {
            return false;
        } else if ($this->post_method) {
            $_SESSION['cache_use_off'] = 1; //if anon user did anything with forms, no longer show them any cache, so they can edit, etc.
            
            return false;
        }
        
        if (qa_request_part(0) == 'admin') {
            return false;
        }
        
        if (is_array($_COOKIE) && !empty($_COOKIE)) {
            foreach ($_COOKIE as $k => $v) {
                if (preg_match('#session#', $k) && strlen($v)) {
                    return false;
                }
                if (preg_match("#fbs_#", $k) && strlen($v)) {
                    return false;
                }
            }
        }
        
        return true;
    } // END do_caching()
    
    /**
     * Checks if the visitor/user will see the cached version.
     * Only non-registered users see the cached version.
     * @return boolean
     */
    public function now_caching()
    {
        if (!QA_CACHING_STATUS) {
            return false;
        }
        
        if (qa_get_logged_in_userid()) {
            return false;
        }
        
        if (qa_request_part(0) == 'admin') {
            return false;
        }
        
        return true;
    }
    
    /**
     * @TODO: Set the same header for html pages
     * @param type $headers
     */
    private function set_headers($headers)
    {
        $headers = headers_list();
    }
    
    /**
     * Returns a unique filepath+filename to store the cache.
     * @return type
     */
    private function get_filename($questionid)
    {
        if (!empty($questionid)) {
            // q2a can run on several domains, ($_SERVER["HTTP_HOST"]), so we could also save this
            
            // $md5 = $questionid.'_'.md5($_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
            $md5 = $questionid;
        } else {
            // md5 of url
            $md5 = md5($_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
        }
        
        // q2apro: CSS is responsive, same CSS/HTML for mobile and desktop
        // return QA_CACHING_DIR . '/' . $md5 . ( $this->mobile? '_mobile': '_desktop' );
        return QA_CACHING_DIR . '/' . $md5;
    }
    
    /**
     * What page does the user see
     * @return boolean
     */
    private function what_page()
    {
        $query = (isset($_REQUEST['qa']) && $_SERVER['REQUEST_METHOD'] == "GET") ? $_REQUEST['qa'] : FALSE;
        if (!$query) {
            return false;
        }
        return $query;
    }
    
    private function compress_html($html)
    {
        require_once QA_PLUGIN_DIR . 'q2apro-caching/tools/minify/HTML.php';
        return Minify_HTML::minify($html);
    }
    
    /**
     * Cache settings form on the admin page.
     * @param type $qa_content
     * @return type
     */
    function option_default($option)
    {
        switch ($option) {
            case 'qa_caching_enabled':
                return false;
            case 'qa_caching_expiration_time':
                return 86400; // 60 s * 60 min * 24 h = 86400 s
            case 'qa_caching_compress':
                return false;
            case 'qa_caching_debug':
                return false;
        }
    }
    
    function admin_form(&$qa_content)
    {
        $saved = false;
        if (qa_clicked('qa_caching_submit_button')) {
            qa_opt('qa_caching_enabled', (int) qa_post_text('qa_caching_enabled' . '_field'));
            qa_opt('qa_caching_expiration_time', (int) qa_post_text('qa_caching_expiration_time' . '_field'));
            qa_opt('qa_caching_compress', (int) qa_post_text('qa_caching_compress' . '_field'));
            qa_opt('qa_caching_debug', (int) qa_post_text('qa_caching_debug' . '_field'));
            $saved = true;
            $msg   = 'Caching settings saved';
        }
        if (qa_clicked('qa_caching_reset_button')) {
            qa_opt('qa_caching_enabled', (int) $this->option_default('qa_caching_enabled'));
            qa_opt('qa_caching_expiration_time', (int) $this->option_default('qa_caching_expiration_time'));
            qa_opt('qa_caching_compress', (int) $this->option_default('qa_caching_compress'));
            qa_opt('qa_caching_debug', (int) $this->option_default('qa_caching_debug'));
            $saved = true;
            $msg   = 'Caching settings reset';
        }
        if (qa_clicked('qa_caching_clear_cache')) {
            $this->clear_cache();
        }
        $rules                               = array();
        $rules['qa_caching_expiration_time'] = 'qa_caching_enabled_field';
        $rules['qa_caching_compress']        = 'qa_caching_enabled_field';
        $rules['qa_caching_debug']           = 'qa_caching_enabled_field';
        qa_set_display_rules($qa_content, $rules);
        return array(
            'ok' => $saved ? $msg : null,
            'fields' => array(
                array(
                    'label' => 'Enable cache:',
                    'type' => 'checkbox',
                    'value' => (int) qa_opt('qa_caching_enabled'),
                    'tags' => 'name="qa_caching_enabled_field" id="qa_caching_enabled_field"'
                ),
                array(
                    'id' => 'qa_caching_expiration_time',
                    'label' => 'Expiration time (refresh page cache):',
                    'type' => 'number',
                    'value' => qa_opt('qa_caching_expiration_time'),
                    'suffix' => 'seconds',
                    'tags' => 'name="qa_caching_expiration_time_field" style="width:80px;"'
                ),
                array(
                    'id' => 'qa_caching_compress',
                    'label' => 'Minify HTML for cached file',
                    'type' => 'checkbox',
                    'value' => (int) qa_opt('qa_caching_compress'),
                    'tags' => 'name="qa_caching_compress_field"'
                ),
                array(
                    'id' => 'qa_caching_debug',
                    'label' => 'Output debug comment (see HTML source in the end)',
                    'type' => 'checkbox',
                    'value' => (int) qa_opt('qa_caching_debug'),
                    'tags' => 'name="qa_caching_debug_field"'
                )
            ),
            'buttons' => array(
                array(
                    'label' => 'Save Changes',
                    'tags' => 'name="qa_caching_submit_button"'
                ),
                array(
                    'label' => 'Reset to Defaults',
                    'tags' => 'name="qa_caching_reset_button"'
                ),
                array(
                    'label' => 'Clear cache',
                    'tags' => 'name="qa_caching_clear_cache"'
                )
            )
        );
    }
}
