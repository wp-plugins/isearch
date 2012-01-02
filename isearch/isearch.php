<?php
/*
  Plugin Name: ISearch
  Plugin URI: http://www.innovativephp.com/isearch
  Description: Innovative Search.
  Version: 1.0
  Author: Rakhitha Nimesh
  Author URI: http://www.innovativephp.com/about
 */

session_start();

function isearch_plugin_scripts() {
    $plugin_dir = WP_PLUGIN_URL . "/";
    wp_deregister_script( 'jquery_plugins' );
    wp_register_script( 'jquery_plugins', 'http://ajax.googleapis.com/ajax/libs/jquery/1.6/jquery.min.js');
    wp_enqueue_script( 'jquery_plugins' );
    wp_enqueue_style("isearch", $plugin_dir . "isearch/css/isearch.css", false, "1.0", "all");

}

add_action('wp_enqueue_scripts', 'isearch_plugin_scripts');

function isearch_header() {
    $site_url = get_option('siteurl');
    $plugin_dir = WP_PLUGIN_URL . "/";
?>

    <script type="text/javascript">
        $(document).ready(function(){
            $("#isearch_text").keyup(function(){

                var search_term = $("#isearch_text").val();
                var data = {
                    action: 'isearch_results_action',
                    search_term: search_term
                };


                $.ajax({
                    type: "POST",
                    url: "<?php echo $site_url;?>/wp-admin/admin-ajax.php",
                    data:data,
                    success: function(res) {

                        $('#isearch_reuslt_list').html(res);

                        $(".post_results_check").click(function(){

                            var result_id = $(this).attr('value');

                            addSearchToStorage(result_id);


                        });
                    }
                });
            });

            $("#isearch_text").focus(function(){

                $("#isearch_text").val('');

            });


            $("#view_tour_isearch").click(function(){

                var data = {
                    action: 'isearch_start_tour_action'
                };

                $.ajax({
                    type: "POST",
                    url: "<?php echo $site_url;?>/wp-admin/admin-ajax.php",
                    data:data,
                    success: function(res) {
                        var result = eval('(' + res + ')');
                        if(result.status == "empty"){
                            alert("Please Select Item(s) To Continue");
                        }else{
                            window.location.href = result.url;
                        }
                    }
                });

            });

            function addSearchToStorage(result_id){

                var data = {
                    action: 'isearch_addto_storage_action',
                    search_id: result_id
                };

                $.ajax({
                    type: "POST",
                    url: "<?php echo $site_url;?>/wp-admin/admin-ajax.php",
                    data:data,
                    success: function(res) {


                    }
                });

            }

            $(".post_results_check").click(function(){

                var result_id = $(this).attr('value');

                addSearchToStorage(result_id);

            });



        });


    </script>

<?php
}

add_action('wp_head', 'isearch_header');


add_action('wp_ajax_nopriv_isearch_results_action', 'isearch_results_callback');
add_action('wp_ajax_isearch_results_action', 'isearch_results_callback');

add_action('wp_ajax_nopriv_isearch_addto_storage_action', 'isearch_addto_storage_callback');
add_action('wp_ajax_isearch_addto_storage_action', 'isearch_addto_storage_callback');

add_action('wp_ajax_nopriv_isearch_start_tour_action', 'isearch_start_tour_callback');
add_action('wp_ajax_isearch_start_tour_action', 'isearch_start_tour_callback');

function get_isearch_options($search_term) {

    global $wpdb, $post;

    $data = array(
		array("option"=>"search_type","value"=>"post","status"=>"1"),
		array("option"=>"search_type","value"=>"page","status"=>"1"),
		array("option"=>"search_field","value"=>"title","status"=>"1"),
		array("option"=>"search_field","value"=>"content","status"=>"1"),
		array("option"=>"search_num_items","value"=>"20","status"=>"1")
		);


    $search_type = array();
    $search_field = array();
    $search_limit = '';

    foreach ($data as $key => $value) {

        if ($value['option'] == 'search_type') {
            array_push($search_type, " post_type='" . $value['value'] . "' ");
        }

        if ($value['option'] == 'search_field') {

            switch ($value['value']) {
                case 'title':
                    if ($value['status'] == '1') {
                        array_push($search_field, " post_title like '%" . $search_term . "%' ");
                    }
                    break;

                case 'content':
                    if ($value['status'] == '1') {
                        array_push($search_field, " post_content like '%" . $search_term . "%' ");
                    }
                    break;
            }
        }

        if ($value['option'] == 'search_num_items') {
            $search_limit = "limit " . $value['value'];
        }
    }

    $data_search['search_type'] = $search_type;
    $data_search['search_field'] = $search_field;
    $data_search['search_num_items'] = $search_limit;




    if (count($data_search['search_type']) != '0' || count($data_search['search_field']) != '0') {
        $sql = 'and ';

        $sql .= "(" . implode(" or ", $data_search['search_type']) . ") and (";

        $sql .= " " . implode(" or ", $data_search['search_field']) . " ";

        $sql = substr($sql, 0, -2);
        $sql .=")  order by post_type desc,post_date desc ";
        $sql.= "  " . $data_search['search_num_items'];
    }



    return $sql;
}

function isearch_start_tour_callback() {

    global $wpdb, $post;
    $search_table = $wpdb->prefix . "posts";


    $url = '';

    if (isset($_SESSION['isearch_saves']) && count($_SESSION['isearch_saves']) != '0') {

        $current_pid = array_shift($_SESSION['isearch_saves']);

        $sql = "select * from $search_table where  ID=" . trim($current_pid);

        $data = $wpdb->get_results($sql, ARRAY_A);

        if (!is_null($data)) { // if we have a matching data row
            foreach ($data as $key => $posts) {

                $url = $posts['guid'];
            }
        }
        echo json_encode(array("status" => '', "url" => $url));
    } else {
        echo json_encode(array("status" => "empty"));
    }
    exit;
}

function isearch_addto_storage_callback() {

    $search_id = isset($_POST['search_id']) ? $_POST['search_id'] : '';

    if (isset($_SESSION['isearch_saves'])) {
        if (!(in_array($search_id, $_SESSION['isearch_saves']))) {
            array_push($_SESSION['isearch_saves'], $search_id);
        } else {
            foreach ($_SESSION['isearch_saves'] as $key => $value) {
                if ($value == $search_id) {
                    unset($_SESSION['isearch_saves'][$key]);
                }
            }
        }
    } else {
        $search_saves = array();
        array_push($search_saves, $search_id);
        $_SESSION['isearch_saves'] = $search_saves;
    }

    echo json_encode($_SESSION['isearch_saves']);
    exit;
}

function isearch_results_callback($type='') {



    global $wpdb, $post;
    $search_table = $wpdb->prefix . "posts";

    $result_links_arrray = array();

    $search_term = isset($_POST['search_term']) ? $_POST['search_term'] : '';

    $sql = get_isearch_options($search_term);

    $sql = "select * from $search_table where  post_status='publish' $sql ";

    $data = $wpdb->get_results($sql, ARRAY_A);

    $content.= "<ul id='isearch_list_ul'>";


    $content.= "<li class='isheaders'>" . count($data) . " Results</li>";

    if (!is_null($data)) { // if we have a matching data row
        if (count($data) == '0') {
            //$content.= "<li class='isitems'>No Posts Found</li>";
        } else {
            foreach ($data as $key => $posts) {

                if ($posts['post_type'] == 'post') {
                    $content.= "<li class='isitems'>
                                    <div class='result_post'>POST  </div>
                                    <div class='result_post_comp'><input id='" . $posts['ID'] . "' class='post_results_check'  type='checkbox' value='" . $posts['ID'] . "' />
                                    <a href='" . $posts['guid'] . "'>  " . $posts['post_title'] . "</a></div>
                                    
                                </li>";
                } else if ($posts['post_type'] == 'page') {
                    $content.= "<li class='isitems'>
                                    <div class='result_page'>PAGE  </div>
                                    <div class='result_page_comp'><input id='" . $posts['ID'] . "' class='post_results_check'  type='checkbox' value='" . $posts['ID'] . "' />
                                    <a href='" . $posts['guid'] . "'>  " . $posts['post_title'] . "</a></div>
                                    
                                </li>";
                }
            }
        }
    } else {
        $content.= "<li class='isitems'>No Posts Found</li>";
    }


    $content.= '</ul>';

    if ($type != 'web') {
        echo $content;
    } else {
        return $content;
    }

    exit;
}

function isearch_init($atts, $content) {

    global $wpdb, $post;

    unset($_SESSION['isearch_saves']);

    $_SESSION['isearch_page_url'] = $_SERVER['REQUEST_URI'];

    $initial_result = isearch_results_callback("web");

    return '<div style="float: left; width:98%; margin: 1% 1% 0pt;">
				<input type="text" value="Type Here To Search" id="isearch_text" name="isearch_text" >
			</div><div id="isearch_reuslt"><div id="isearch_reuslt_list">' . $initial_result . '</div><div id="tour_btn_start"><input type="button"
                            id="view_tour_isearch" value="Start Tour"/></div></div>';
}

add_shortcode('isearch_page', 'isearch_init');

function add_floating_next($content) {


	$site_url = get_option('siteurl');

	global $wpdb, $post;
	$search_table = $wpdb->prefix . "posts";


	if ($post->post_title == 'isearch') {
		unset($_SESSION['isearch_saves']);
	}


	if (isset($_SESSION['isearch_saves']) && count($_SESSION['isearch_saves']) != '0') {

		if (in_array($post->ID, $_SESSION['isearch_saves'])) {

			foreach ($_SESSION['isearch_saves'] as $key => $value) {
				if ($value == $post->ID) {

					unset($_SESSION['isearch_saves'][$key]);
					$_SESSION['isearch_saves'] = array_values($_SESSION['isearch_saves']);
				}
			}
		}



		if (count($_SESSION['isearch_saves']) != '0') {
			$current_pid = array_shift($_SESSION['isearch_saves']);
			array_unshift($_SESSION['isearch_saves'], $current_pid);




			$sql = "select * from $search_table where  ID=" . trim($current_pid);

			$data = $wpdb->get_results($sql, ARRAY_A);

			if (!is_null($data)) { // if we have a matching data row
				foreach ($data as $key => $posts) {

					$url = $posts['guid'];
					$ptitle = $posts['post_title'];
				}
			}
			?>
<script>
	$(document).ready(function(){
		var heights = $(window).height();
		var widths  = $(window).width();
		var top = $(window).height() - parseInt(60);

		$('body').append('<p class="iserach_floater" style="top:'+top+'px;"><a href="<?php echo $url; ?>">Next </a></p>');
	});
</script>
			<?php
		}else if(isset($_SESSION['isearch_saves'])) {
			?>
<script>
	$(document).ready(function(){

		var heights = $(window).height();
		var widths  = $(window).width();
		var top = $(window).height() - parseInt(105);


		$('body').append('<div class="iserach_floater_finish" style="top:'+top+'px;"><div  id="isearch_user_msg">You Have Successfully Completed ISearch Tour - <a href="<?php echo $_SESSION['isearch_page_url'];?>">Click Here To Start Another Tour</a></div></div>');

	});
</script>
			<?php

			unset ($_SESSION['isearch_saves']);

		}
	}
	?>
<style>
	.iserach_floater{
		background-color: #184481;
		color: #FFFFFF;
		font-size: 20px;
		font-weight: bold;
		height: 30px;
		left: 0;
		top:0;
		padding: 10px 15px 20px;
		position: fixed;
		width: 150px;

	}
	.iserach_floater_finish{
		background-color: #184481;
		color: #FFFFFF;
		font-size: 30px;
		font-weight: bold;
		height: 75px;
		left: 0;
		opacity: 0.8;
		padding: 10px 15px 20px;
		position: fixed;
		width: 100%;
		z-index: 1000;
	}
	.iserach_floater a{
		color: #fff;
		font-weight: bold;
	}
	.isearch_res{
		background-color: #EEEEEE;
		border: 2px solid #434343;
		color: #000000;
		float: left;
		margin: 0 10px;
		padding: 0 20px;
	}

	#isearch_user_msg{
		width:100%;text-shadow: 3px 2px 1px #5D73B5;width: 100%;
	}
	#isearch_user_msg a{
		color: #fff;
	}
</style>
	<?php
	return $content;
}

add_filter('the_content', 'add_floating_next');
?>
