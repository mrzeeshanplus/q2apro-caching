<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
    header('Location: ../../');
    exit;
}

// require_once QA_PLUGIN_DIR.'q2apro-caching/qa-caching-main.php';
require_once QA_HTML_THEME_LAYER_DIRECTORY.'qa-caching-main.php';

class qa_html_theme_layer extends qa_html_theme_base 
{
    public function doctype() 
	{
        $main = new qa_caching_main;
		
        if($main->now_caching()) 
		{
            if(isset($this->content['notices'])) 
			{
                unset($this->content['notices']);
            }
        }
        qa_html_theme_base::doctype();
    }
}
