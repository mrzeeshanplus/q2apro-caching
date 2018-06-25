<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}
require_once QA_PLUGIN_DIR.'q2apro-caching/qa-caching-main.php';

class qa_caching_event
{	
	public function process_event($event, $userid, $handle, $cookieid, $params)
	{
		// update each question on changes made
		if(!empty($params['postid']))
		{
			$postid = $params['postid'];
			
			// *** $parentid = $params['parentid'] // might be used for better performance
			
			// get post data
			$postdata = qa_db_read_one_assoc(
							qa_db_query_sub('
								SELECT type, parentid FROM `^posts` 
								WHERE `postid` = #
								', $postid), true);
			$questionid = null;
			
			if(!empty($postdata['type']))
			{
				if($postdata['type']=='Q')
				{
					$questionid = $postid;
				}
				else if($postdata['type']=='A' && !empty($postdata['parentid']))
				{
					$questionid = $postdata['parentid'];
				}
				else if($postdata['type']=='C' && !empty($postdata['parentid']))
				{
					// C parent is A or Q - get post data of parent 
					$parentdata = qa_db_read_one_assoc(
										qa_db_query_sub('
											SELECT type, parentid FROM `^posts` 
											WHERE `postid` = #
											', $postdata['parentid']), true);
					if($parentdata['type']=='Q')
					{
						$questionid = $postdata['parentid'];
					}
					else if($parentdata['type']=='A')
					{
						// A parent is always Q
						$questionid = $parentdata['parentid'];
					}
				}
			}
			
			if(!empty($questionid))
			{
				$main = new qa_caching_main;
				$main->clear_cache($questionid);
			}
		}
		/*
		// do not clear entire cache anymore
		else
		{
			// clears entire cache
			$main = new qa_caching_main;
			$main->clear_cache();
		}
		*/
		
	} // END process_event
}

class qa_caching_session_reset_event 
{
	public function process_event($event, $userid, $handle, $cookieid, $params) 
	{
		if($event == 'u_login')
		{
			// turn off the flag if login happened
			if(isset($_SESSION['cache_use_off']))
			{
				unset($_SESSION['cache_use_off']);
			}
		}
	}
}
