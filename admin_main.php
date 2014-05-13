<?php
/*
* Output main admin site
*/
date_default_timezone_set('Asia/Shanghai');//'Asia/Shanghai'   亚洲/上海

function k5_options()
{
	global $wpdb;
	global $k5_tables;
	global $site_data;
	global $id;

	global $site_title;
	global $site_url;
	global $site_url_md5;
	global $site_created_at;
	global $site_crawled;
	global $site_id;

	$err = "";
	global $site_validation_failed;

	/* UPDATE DATABASE */
	if(isset($_GET['k5_install_db'])){
		if($_GET['k5_install_db'] == '1'){
			// Run the installation function
			k5_install();
		}
	}

	/* VALIDATION AND POST-DATA */
	// Check if we need to add/edit a site
	if(isset($_POST['site_save'])){
		// Getting edit ID if we have one
		if(isset($_POST['site_id'])){
            $id = $_POST['site_id'];

			// Valid id?
			if(!is_numeric($id)){
				$id = 0;
			}
		}else{
			$id = 0;
		}

        $site_data = array(
            'url' => $_POST['site_url'],
        );

		// Validation
		if($_POST['site_url'] == ""){
			$err.= '<p>'.__('Crawl URL 是必须填写的，亲.', 'k5Crawler').'</p>';
		}

		if($err == ""){
			// We need to add/edit a site
			if($id == 0){
				// Add new site
				if($wpdb->get_var($wpdb->prepare("SELECT id FROM `".$k5_tables['sites']."` WHERE (url = %s AND created_at = %s) OR title = %s",$_POST['site_url'])) == null){
					$wpdb->insert(
								$k5_tables['sites'],
								array(
									'url_md5' => md5($_POST['site_url']),
									'url' => $_POST['site_url'],
									'user_id' => get_current_user_id(),
                                ),
								array('%s', '%s' , '%d')
					);
				}else{
					$err.= __('不要重复添加相同的URL哦，亲', 'k5Crawler');
					$site_validation_failed = 1;
				}
			}else{
				// Edit site
				$wpdb->update(
							$k5_tables['sites'],
							array(
                                'url_md5' => md5($_POST['site_url']),
								'created_at' => date("Y-m-d H:i:s"),
								'url' => $_POST['site_url'],
                                'user_id' => get_current_user_id(),
                            ),
							array('id' => $id),
							array('%s', '%s','%s', '%d'),
							array('%d')
				);
			}
		}else{
			$site_validation_failed = 1;
		}
	}

	/* CHECK IF WE ARE GOING TO DELETE sites */
	if(isset($_GET['site_del_id'])){
		$exist = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM `".$k5_tables['sites']."` WHERE id = %d;", $_GET['site_del_id']));

		if($exist != 0){
			// Delete the site
			$wpdb->query($wpdb->prepare("DELETE FROM `".$k5_tables['sites']."` WHERE `id` = %d;", $_GET['site_del_id']));
		}
	}

	/* SHOW OPTIONS site */

	echo '<div class="wrap">
			<div id="icon-tools" class="icon32">
				<br />
			</div>
			<h2>k5Crawler 配置</h2>';

			if($err != ""){
				echo "<div class='err'>$err</div>";
			}

				//echo '<p>'.__('k5Crawler.' ,'k5Crawler').'</p>';

		// If we're editing a site or creating a new one, we want to show the form
		//if($id != "" || isset($_GET['site_create']) || $site_validation_failed == 1){
			//echo "<script type='text/javascript'>toggleLayer('k5_create_new_site');</script>";
            k5_create_new_site($site_data);
            echo '</p>';
		//}

	/* k5Crawler installations error tester */
	$dbTest = false;
	foreach($k5_tables as $table){
		if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			// Table doesnt exist!
			$dbTest = true;
		}
	}

	if($dbTest){
		// Show the database-error handler
		echo '<h3>'.__('Update database', 'k5Crawler').'</h3>
			<p>'.__('Your database doesn&apos;t include the tables needed for k5Crawler to run. To install the tables, push the button below:', 'k5Crawler').'</p>
			<p><a href="'.$_SERVER['REQUEST_URI'].'&k5_install_db=1" class="button-primary">'.__('Install database tables', 'k5Crawler').'</a></p>';
	}else{

			echo '<h3>'.__('管理站点', 'k5Crawler').'</h3>';

					// Get site_count
					$site_count = $wpdb->get_var("SELECT Count(id) FROM `".$k5_tables['sites']."` WHERE user_id = ".get_current_user_id());

					// If there's any sites, we need to show them
					if($site_count != 0){
                        // Display pagination area
                        $paged = isset($_GET['paged'])?$_GET['paged']:1;
                        $paged_start = ($paged-1)*10;
                        if($site_count > 10) k5_paginate($site_count, $paged);

						$sites = $wpdb->get_results("SELECT * FROM `".$k5_tables['sites']."` WHERE user_id = ".get_current_user_id()." ORDER BY created_at DESC LIMIT $paged_start,10 ");

						// Display list of all sites
						echo '<table class="widefat">
								<thead>
									<tr>
										<th style="width:8%;">'.__('Id', 'k5Crawler').'</th>
										<th style="width:45%;">'.__('URL', 'k5Crawler').'</th>
										<th>'.__('是否已抓取', 'k5Crawler').'</th>
										<th>'.__('添加时间', 'k5Crawler').'</th>
										<th>'.__('用户', 'k5Crawler').'</th>
									</tr>
								</thead>
								<tfoot>
									<tr>
										<th style="width:8%;">'.__('Id', 'k5Crawler').'</th>
										<th style="width:45%;">'.__('URL', 'k5Crawler').'</th>
										<th>'.__('是否已抓取', 'k5Crawler').'</th>
										<th>'.__('添加时间', 'k5Crawler').'</th>
										<th>'.__('用户', 'k5Crawler').'</th>
									</tr>
								</tfoot>
								<tbody>';

						foreach($sites as $site){
                            $user = get_user_by('id', $site->user_id);
                            $largestr = (strlen(stripcslashes($site->url))>100) ? '...':'';
							echo "<tr>
									<td>".$site->id."
									<div class='row-actions'>
										<span class='edit'><a href='".$_SERVER['REQUEST_URI']."&site_id=$site->id'>".__('编辑', 'k5Crawler')."</a> |</span>
										<span class='delete'><a href=\"javascript: confirmMsg('".__('确定要删除吗?', 'k5Crawler')."', '".$_SERVER['REQUEST_URI']."&site_del_id=$site->id');\">".__('删除', 'k5Crawler')."</a></span>
									</div>
									</td>
									<td>".mb_substr(stripcslashes($site->url), 0, 100).$largestr."</td>
									<td>".$site->crawled."</td>
									<td>".$site->created_at."</td>
									<td>".$user->display_name."</td>
								</tr>";
						}

						echo '</tbody>
							</table>';
					}else{
						echo '<p class="italic">'.__('暂时没有站点记录.', 'k5Crawler').'</p>';
					}

					//echo "<p><a class='button-primary' href='?page=k5_options&site_create=1'>".__('添加站点', 'k5Crawler')."</a>";


		  echo '</div>';
	}
}

/*
* Prints out the 'Create new site'-form
*/
function k5_create_new_site($site_data)
{
	global $wpdb;
	global $k5_tables;

	global $title;
	global $url;
	global $url_md5;
	global $created_at;
	global $crawled;
	global $id;


	// Checks if we're going to edit a site
	if(isset($_GET['site_id'])){
		// Get the site ID
		$id = $_GET['site_id'];

		// Check if it's a numeric value
		if(is_numeric($id)){
			// Get the current data
			$data = $wpdb->get_row("SELECT * FROM `".$k5_tables['sites']."` WHERE id = $id");

			// prepare the data to use as values
			$title = stripcslashes($data->title);
			$crawled = stripcslashes($data->crawled);
			$url = stripcslashes($data->url);
			$created_at = stripcslashes($data->created_at);
			$url_md5 = stripcslashes($data->url_md5);
			$id = "<input type='hidden' name='site_id' value='$data->id' />";
		}
	}

	// Check if we need to load failed validation info
	global $site_validation_failed;
	if($site_validation_failed == 1){
		// prepare the data to use as values
		$url = stripcslashes($site_data['url']);
		$url_md5 = stripcslashes($site_data['url_md5']);

		if($site_data['id'] != 0){
			$id = "<input type='hidden' name='site_id' value='".$site_data['id']."' />";
		}
	}


	// Echo the form
	echo "<form method='post' action='?page=k5_options'>
			<div id='poststuff' class='metabox-holder'>
			<div class='stuffbox' id='k5_create_new_site'><h3><label for='site_title'>".($id == "" ? __('添加站点', 'k5Crawler') : __('编辑站点', 'k5Crawler'))."</label></h3>";

	echo "
			<div class='inside'>
			<table class='form-table'>
				<tbody>
					<tr>
						<td><label for='site_url'>".__('Crawl URL*:', 'k5Crawler')."</label></td>
						<td><input type='text' id='site_url' name='site_url' value='$url' style='width: 100%;' /></td>
					</tr>
					<tr>
						<td colspan='2'><input type='submit' class='button-primary' name='site_save' value='".__('保存', 'k5Crawler')."' /> <a class='button-secondary' href='?page=k5_options'>".__('取消', 'k5Crawler')."</a></td>
					</tr>
				</tbody>
			</table>
			</div>
		</div></div></form>";
}

/*
* render pagination area if needed (items > 10) , and 10 items per page
*/
function k5_paginate($sum, $page)
{
    $page_num = 10;
    $pages = ceil($sum/$page_num);
$page_last = ($page>1)?($page-1):1;
$page_next = ($page<$sum)?($page+1):$sum;
    echo <<<EOF
<div class="tablenav">
    <div class="tablenav-pages">
        <span class="displaying-num">{$sum}个项目</span>
        <span class="pagination-links">
            <a class="first-page" title="前往第一页" href="?page=k5_options&paged=1">«</a>
            <a class="prev-page" title="前往上一页"  href="?page=k5_options&paged={$page_last}">‹</a>
            <span class="paging-input">
                第<input class="current-page" title="当前页面" type="text" name="paged" value="{$page}" size="1">页，
                共<span class="total-pages">{$pages}</span>页
            </span>
            <a class="next-page" title="前往下一页"   href="?page=k5_options&paged={$page_next}">›</a>
            <a class="last-page" title="前往最后一页" href="?page=k5_options&paged={$pages}">»</a>
        </span>
    </div>
</div>
EOF;
}
?>
