<?php

 /*
 Open Social Media Monitoring
 http://www.open-social-media-monitoring.net

 Copyright (c) 2011 Openstream Internet Solutions

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License v3 (2007)
 as published by the Free Software Foundation.
 */

 class Projects extends Application{

  /*
  *  Inside class routing
  *
  *  @param array
  *  @return void
  */
  function __construct($args){
   // Searching for a method name and calling either it or default method
   if(is_array($args) && count($args) && method_exists($this, strtolower($args[0]).'Action')){
	call_user_func_array(array($this, strtolower($args[0]).'Action'), array_slice($args, 1));
   }else{
	$this->defaultAction();
   }
  }

  /*
  *  Prints out the XML of search results for defined queries
  *
  *  @param none
  *  @return void
  */
  function getinfluencersAction(){
   global $prefix;
   
   $influencers_to_show = 5;
   $influencer_arr = array();
   
   $from = strtotime(date('Y-m-d', $_GET['from']));
   $to = strtotime(date('Y-m-d', $_GET['to']));
   
   $query = 'SELECT *, COUNT(*) as cnt 
               FROM '.$prefix.'search      
              WHERE query_id IN ('.urldecode($_GET['ids']).')
			    AND search_published > '.$from.'
				AND search_published < '.$to.'
		   GROUP BY search_author_name';
   $res = mysql_query($query);
   while($res && $influencer = mysql_fetch_object($res)){
    $influencer_arr[$influencer->search_author_name] = array('name' => $influencer->search_author_name, 'uri' => $influencer->search_author_uri, 'cnt' => $influencer->cnt);
   }
   
   $query = 'SELECT *, SUM(index_count) as cnt
               FROM '.$prefix.'search_influencers_index
              WHERE query_id IN ('.urldecode($_GET['ids']).')
				AND index_date > '.$from.'
				AND index_date < '.$to.'
		   GROUP BY search_author_name';
   $res = mysql_query($query);
   while($res && $influencer = mysql_fetch_object($res)){
    $influencer_arr[$influencer->search_author_name] = array(
	 'name' => $influencer->search_author_name,
	 'uri' => $influencer->search_author_uri,
	 'cnt' => isset($influencer_arr[$influencer->search_author_name]) ? $influencer_arr[$influencer->search_author_name]['cnt'] + $influencer->cnt : $influencer->cnt
	);
   }
   
   $this->aasort($influencer_arr, 'cnt');
   
   echo '<h3>Top Influencers</h3>';
   while((list($key, $influencer) = each($influencer_arr)) && $influencers_to_show--){
    echo '<div class="left user"><a href="'.$influencer['uri'].'" target="_blank">'.$influencer['name'].'</a></div><div class="right">'.$influencer['cnt'].' mention'.($influencer['cnt'] > 1 ? 's' : '').'</div><div class="clear"></div>';
   }
  }
  
  /*
  *  Prints out the XML of search results for defined queries
  *
  *  @param string
  *  @return void
  */
  function wirexmlAction(){
   global $prefix;

   // Array indexes are 0-based, jCarousel positions are 1-based.
   $first = max(0, intval($_GET['first']) - 1);
   $last  = max($first + 1, intval($_GET['last']) - 1);
   $length = $last - $first + 1;
   
   $from = strtotime(date('Y-m-d', $_GET['from']));
   $to = strtotime(date('Y-m-d', $_GET['to']));

   $query = 'SELECT * 
               FROM '.$prefix.'search      
              WHERE query_id IN ('.urldecode($_GET['ids']).')
				AND search_published > '.$from.'
				AND search_published < '.($to + 24*3600).'
		   ORDER BY search_published DESC';

   $res = mysql_query($query);   
   $cnt = 0;
   
   header('Content-Type: text/xml');
   echo '<data>'."\n".'<total>'.mysql_num_rows($res).'</total>'."\n";

   while($res && $search = mysql_fetch_object($res)){
	if($cnt >= $first && $cnt <= $last){
     echo '<node>'."\n".'<source>'.$search->search_source.'</source>'."\n";
     if($search->search_source == 'facebook'){
      echo '<image>images/fb.jpg</image>'."\n";
	  echo '<link>http://www.facebook.com/profile.php?id='.preg_replace('/_.*$/', '', $search->search_outer_id).'</link>'."\n";
     }else{
      $query = 'SELECT * FROM '.$prefix.'search_link WHERE search_id = '.(int)$search->search_id;
      $re2 = mysql_query($query);
      while($re2 && $obj = mysql_fetch_object($re2)){
	   $link = json_decode(stripslashes($obj->search_link_str));
       if($link->{'@attributes'}->type == 'image/png'){
	    // We are not outputting the link here as there might be more then one image/png @attributes
        $link_output = '<link>'.$search->search_author_uri.'</link>'."\n".'<image>'.$link->{'@attributes'}->href.'</image>'."\n";
       }
      }
	  echo $link_output;
     }
     echo preg_replace('/\&/ism', '&amp;', '<author>'.stripslashes($search->search_author_name).'</author>'."\n".'<date>'.date('F jS, Y H:i', $search->search_published).'</date>'."\n".'<content>'.substr(strip_tags(stripslashes($search->search_content)), 0, 200).'</content>')."\n";
	 echo '</node>'."\n";	 
    }
	$cnt++;
   }
   echo '</data>';
  }
  
  /*
  *  Displayes queries list screen
  *
  *  @param int
  *  @return void
  */
  function queriesAction($project_id){
   global $prefix;
  
   a_header();

   echo '<table cellpadding=5 cellspacing=0 width=100% class=t1>
          <tr><td class=he2><b>Keyword</b></td>
              <td class=he2 width=150><b>Language</b></td>
              <td class=he2 width=150><b>Geo Code</b></td>
              <td align=center class=he2 width=190><b>Action</b></td>
          </tr>';
   $query = 'SELECT q.* FROM '.$prefix.'query q INNER JOIN '.$prefix.'project_to_query p2q ON p2q.query_id = q.query_id WHERE p2q.project_id = '.(int)$project_id;
   $res = mysql_query($query);
   while($res && $page = mysql_fetch_object($res))
    echo '<tr>
           <td align=center class=rw>'.$page->query_q.'</td>
           <td class=rw align=center>'.($page->query_lang ? $page->query_lang : '-').'</td>
           <td class=rw align=center>'.($page->query_geocode ? $page->query_geocode : '-').'</td>
           <td class=rw align=center>
            <a href="'.$this->getUrl('projects/results/query/'.$page->query_id).'">results</a> -
            <a href="'.$this->getUrl('projects/editquery/'.(int)$project_id.'/'.$page->query_id).'" onclick="return confirm(\'Are you sure? All keyword stored data will be wiped out.\');">edit</a> - 
            <a href="'.$this->getUrl('projects/delete/'.(int)$project_id.'/'.$page->query_id).'" onclick="return confirm(\'Are you sure? All keyword stored data will be wiped out.\');">delete</a>
           </td>
          </tr>';
   echo '</table><br /><input type="button" class="bu" value="Add Query" onclick="location.href = \''.$this->getUrl('projects/editquery/'.(int)$project_id).'\'" />';

   a_footer();  
  }
  
  /*
  *  Deletes queries, searches, links and relations
  *
  *  @param array
  *  @return void
  */
  function deleteAction($project_id, $query_id = 0){
   global $prefix;
   
   if(!$query_id){
    // If only $project_id is passed we assuming that whole project have to be deleted
    $query = 'SELECT query_id FROM '.$prefix.'project_to_query WHERE project_id = '.(int)$project_id;
	$res = mysql_query($query);
	$query_array = array();
	while($res && $query = mysql_fetch_object($res)){
	 $query_array[] = $query->query_id;
	}
	$query = 'DELETE FROM '.$prefix.'project WHERE project_id = '.(int)$project_id;
	mysql_query($query);
   }else{
    $query_array = array((int)$query_id);
   }
   
   if(is_array($query_array)){
    $this->deleteSearchNodes($query_array);
    $query_array = implode(', ', $query_array);
	$query = 'DELETE FROM '.$prefix.'query WHERE query_id IN ('.$query_array.')';
	mysql_query($query);
	$query = 'DELETE FROM '.$prefix.'project_to_query WHERE query_id IN ('.$query_array.')';
	mysql_query($query);
   }
   
   header('Location: '.($query_id ? $this->getUrl('projects/queries/'.$project_id) : $this->getUrl('projects')));
  }
  
  /*
  *  Deletes searches and links of queries array
  *
  *  @param array
  *  @return void
  */
  function deleteSearchNodes($query_array){
   global $prefix;
   
   if(is_array($query_array)){
    $query_array = implode(', ', $query_array);
    $query = 'SELECT search_id FROM '.$prefix.'search WHERE query_id IN ('.$query_array.')';
	$res = mysql_query($query);
	$search_array = array();
	while($res && $search = mysql_fetch_object($res)){
	 $search_array[] = $search->search_id;
	}
	$search_array = implode(', ', $search_array);
	$query = 'DELETE FROM '.$prefix.'search_link WHERE search_id IN ('.$search_array.')';
	mysql_query($query);
	$query = 'DELETE FROM '.$prefix.'search WHERE search_id IN ('.$search_array.')';
	mysql_query($query);
   } 
  }
  
  /*
  *  Displays projects list screen
  *
  *  @param none
  *  @return void
  */
  function defaultAction(){
   global $prefix;
  
   a_header();

   echo '<table cellpadding=5 cellspacing=0 width=100% class="t1t">';
   $query = 'SELECT * FROM '.$prefix.'project';
   $res = mysql_query($query);
   while($res && $page = mysql_fetch_object($res)){
    $query = 'SELECT q.*, COUNT(s.search_id) cnt, s.search_published
	            FROM '.$prefix.'project_to_query p2q 
		   LEFT JOIN '.$prefix.'query q ON q.query_id = p2q.query_id
		   LEFT JOIN '.$prefix.'search s ON s.query_id = p2q.query_id
			   WHERE p2q.project_id = "'.$page->project_id.'"
			GROUP BY p2q.query_id
			ORDER BY s.search_published ASC';
	$re0 = mysql_query($query);
	$keywords = array();
	$cnt = $first_date = 0;
	while($re0 && $keyword = mysql_fetch_object($re0)){
	 $keywords[] = '<a href="'.$this->getUrl('projects/results/query/'.$keyword->query_id).'">'.$keyword->query_q.($keyword->query_lang ? ' ('.$keyword->query_lang.')' : '').'</a>';
	 $cnt += $keyword->cnt;
	 $first_date = !$first_date || $first_date > $keyword->search_published ? $keyword->search_published : $first_date;
	}
	$keywords = implode(', ', $keywords);
    echo '<tr>
           <td class="rw"><h4><a href="'.$this->getUrl('projects/results/project/'.$page->project_id).'">'.$page->project_name.'</a></h4>Weekly mentions: '.round($cnt/ceil((time() - $first_date)/(3600*24*7))).' | Daily Mentions: '.round($cnt/ceil((time() - $first_date)/(3600*24))).'<br />Keywords: '.$keywords.'</td>
           <td class="rw" style="padding-right:25px;" align="right">
		    <a href="'.$this->getUrl('projects/results/project/'.$page->project_id).'">results</a> -
            <a href="'.$this->getUrl('projects/queries/'.$page->project_id).'">keywords</a> -
            <a href="'.$this->getUrl('projects/edit/'.$page->project_id).'">edit project</a> -
            <a href="'.$this->getUrl('projects/delete/'.$page->project_id).'" onclick="return confirm(\'Are you sure? All keyword(s) stored data will be wiped out.\');">delete project</a>
           </td>
          </tr>';
   }	  
   echo '</table><br /><input type="button" class="bu" value="Add Project" onclick="location.href = \''.$this->getUrl('projects/edit').'\'" />';

   a_footer();  
  }
  
  /*
  *  Deletes edit project screen
  *
  *  @param mixed
  *  @return void
  */
  function editAction($id = 0){
   global $prefix;
   
    a_header();

    if($id){
     $query = 'SELECT * FROM '.$prefix.'project WHERE project_id = '.$id;
     $res = mysql_query($query);
     if($res && mysql_num_rows($res)){
      $page = mysql_fetch_object($res);
	 }
	}

    echo '<form method=post action="'.$this->getUrl('projects/save').'"><input type="hidden" name="id" value="'.(int)$id.'" />
           <table>
            <tr><td>Project Name:</td><td><input type="text" value="'.$page->project_name.'" name="project_name" /></td></tr>
           </table><br />
           <div align="center"><input type=submit value="&nbsp; Save &nbsp;" class=bu> <input type=button value="Cancel" class=bu onclick="location.href = \''.$this->getUrl('projects').'\'"></div>
          </form>';
          
    a_footer();  
  }
  
  /*
  *  Stores project changes
  *
  *  @param none
  *  @return void
  */
  function saveAction(){
   global $prefix;
   
   $query = 'SELECT * FROM '.$prefix.'project WHERE project_id = '.$_POST['id'];
   $res = mysql_query($query);
   if($res && mysql_num_rows($res)){
     $query = 'UPDATE '.$prefix.'project 
                  SET project_name = "'.$_POST['project_name'].'" 
                WHERE project_id = "'.$_POST['id'].'"';
   }else{
     $query = 'INSERT INTO '.$prefix.'project 
                  SET project_name = "'.$_POST['project_name'].'"';
   }
   $res = mysql_query($query);
   header('Location: '.$this->getUrl('projects'));
  }
  
  /*
  *  Displays edit query screen
  *
  *  @param int, mixed
  *  @return void
  */
  function editqueryAction($project_id, $query_id = 0){
   global $prefix;
   
    a_header();

    if($query_id){
     $query = 'SELECT * 
	             FROM '.$prefix.'query
				WHERE query_id = '.$query_id;
     $res = mysql_query($query);
     if($res && mysql_num_rows($res)){
      $page = mysql_fetch_object($res);
	 }
	}
	
	$avail_lang = array('all' => '', 'en' => 'en', 'de' => 'de');
	$options = '';
	while(list($key, $val) = each($avail_lang)){
	 $options .= '<option value="'.$val.'"'.($val == $page->query_lang ? ' selected' : '').'>'.$key.'</option>';
	}

	$radio_distanceunit = '';
	if( $page->query_distanceunit == 'mi' )
	{
		$radio_distanceunit = '<input type="radio" name="query_distanceunit" value="mi" checked>miles
							   <input type="radio" name="query_distanceunit" value="km" >kilometer';
	}
	else
	{
		$radio_distanceunit = '<input type="radio" name="query_distanceunit" value="mi" >miles
							   <input type="radio" name="query_distanceunit" value="km" checked>kilometer';
	}
	
    echo '<form method=post action="'.$this->getUrl('projects/savequery').'"><input type=hidden name=id value="'.(int)$query_id.'" /><input type="hidden" name="project_id" value="'.$project_id.'" />
           <table>
            <tr><td>Keyword:</td><td><input type="text" value="'.$page->query_q.'" name="query_q" /></td></tr>
            <tr><td>Language:</td><td><select name="query_lang">'.$options.'</select></td></tr>
            <!--tr><td>Geo Code:</td><td><input type="text" value="'.$page->query_geocode.'" name="query_geocode" /></td></tr-->
			<tr><td>Near this place:</td><td><input type="text" value="'.$page->query_nearplace.'" name="query_nearplace" /></td></tr>
			<tr><td>Within this distance:</td><td><input type="text" value="'.$page->query_distance.'" name="query_distance" /></td></tr>
			<tr><td></td><td>'.$radio_distanceunit.'</td></tr>
           </table><br />
           <div align="center"><input type=submit value="&nbsp; Save &nbsp;" class=bu> <input type=button value="Cancel" class=bu onclick="location.href = \''.$this->getUrl('projects/queries/'.$project_id).'\'"></div>
          </form>';
          
    a_footer();  
  }
  
  /*
  *  Requests Geocode from twitter geo/search API
  *
  *  @param string, int, string
  *  @return string
  */
  
  private function getGeocode( $query_nearplace, $query_distance, $query_distanceunit ) {

		if( empty( $query_nearplace ) )
			return '';
  
		// Twitter geo/search request
		$twitter = file_get_contents( 'http://api.twitter.com/1/geo/search.json?query='.urlencode($query_nearplace) );
		$json = @json_decode($twitter, true);
		
		// Number of places returned
		$nrPlaces = count( $json['result']['places'] );
		$place = 0;
	
		// Get the index of the first place with the place_type city
		for( $i = 0; $i < $nrPlaces; $i++){
			if( $json['result']['places'][$i]['place_type'] == 'city' ){
				$place = $i;
				break;
			}
		}
		
		// Get the coordinates of the found city
		$coordinates = $json['result']['places'][$place]['bounding_box']['coordinates'][0];
		
		$nrCoords = count( $coordinates );
		$long = 0.0;
		$lat  = 0.0;
		
		// Calculate the middle of the city area
		for( $i = 0; $i < $nrCoords; $i++ ){
			$long += $coordinates[$i][0];
			$lat  += $coordinates[$i][1];
		}
		$long /= $nrCoords;
		$lat  /= $nrCoords;
		
		//return the geocode
		return $lat.','.$long.','.$query_distance.$query_distanceunit;
}
  
  /*
  *  Stores query changes
  *
  *  @param none
  *  @return void
  */
  function savequeryAction(){
   global $prefix;
   
   $query_geocode = $this->getGeocode( $_POST['query_nearplace'], $_POST['query_distance'], $_POST['query_distanceunit']);
      
   $query = 'SELECT * FROM '.$prefix.'query WHERE query_id = '.$_POST['id'];
   $res = mysql_query($query);
   if($res && mysql_num_rows($res)){
    $this->deleteSearchNodes(array($_POST['id']));
    $query = 'UPDATE '.$prefix.'query 
                 SET query_q = "'.$_POST['query_q'].'", 
                     query_lang = "'.$_POST['query_lang'].'",
                     query_geocode = "'.$query_geocode.'",
					 query_nearplace = "'.$_POST['query_nearplace'].'",
					 query_distance  = "'.$_POST['query_distance'].'",
					 query_distanceunit = "'.$_POST['query_distanceunit'].'"
               WHERE query_id = "'.$_POST['id'].'"';
    $res = mysql_query($query);
   }elseif(isset($_POST['project_id']) && (int)$_POST['project_id']){
    $query = 'INSERT INTO '.$prefix.'query 
                 SET query_q = "'.$_POST['query_q'].'", 
                     query_lang = "'.$_POST['query_lang'].'",
                     query_geocode = "'.$query_geocode.'",
					 query_nearplace = "'.$_POST['query_nearplace'].'",
					 query_distance  = "'.$_POST['query_distance'].'",
					 query_distanceunit = "'.$_POST['query_distanceunit'].'"';
    $res = mysql_query($query);
	$query = 'INSERT INTO '.$prefix.'project_to_query 
	                  SET project_id = '.(int)$_POST['project_id'].',
					      query_id = '.mysql_insert_id();
    $res = mysql_query($query);
   }
   header('Location: '.$this->getUrl('projects/queries/'.$_POST['project_id']));
  }
    
  
  /*
  *  Printing results page graph
  *
  *  @param string, int
  *  @return void
  */
  private function graph($type, $id){
   global $prefix;
  
   // Fetching project/query information for graph title
   $query = 'SELECT * FROM '.$prefix.$type.' WHERE '.$type.'_id = '.(int)$id;
   $res = mysql_query($query);
   $info = mysql_fetch_object($res);
   if($type == 'query'){
    $title = 'Daily Twitter vs. Facebook';
    $subtitle = 'Keyword: '.$info->query_q.' Language: '.$info->query_lang;
   }else{
    $title = 'Project: '.$info->project_name;
//	$subtitle = 'Daily keyword drilldown';
   }
   
   // Printing out graph JS code
?>

<script src="http://code.highcharts.com/stock/highstock.js"></script>
<script type="text/javascript" src="js/modules/exporting.js"></script>
<script type="text/javascript">
                
 var chart;
 jQuery(document).ready(function() {
  var options = {
   chart: { renderTo: 'container', marginTop: 57, events: {
    redraw: function(){
     extrems = this.xAxis[0].getExtremes();
     theModelCarousel.reset();
     updateTopInfluencers(extrems.min/1000, extrems.max/1000);	
    },
    load: function(){
     extrems = this.xAxis[0].getExtremes();
     updateTopInfluencers(extrems.min/1000, extrems.max/1000);
    }
   } },
   title: { text: '<?php echo $title ?>' },
   subtitle: { text: '<?php echo $subtitle ?>' },
   rangeSelector: { selected: 0 },
   yAxis: [{ // left y axis
    title: { text: null },
    labels: { align: 'left', x: 3, y: 16, formatter: function() { return Highcharts.numberFormat(this.value, 0); } },
    showFirstLabel: false
   }, { // right y axis
    linkedTo: 0,
    gridLineWidth: 0,
    opposite: true,
    title: { text: null },
    labels: { align: 'right', x: -3, y: 16, formatter: function() { return Highcharts.numberFormat(this.value, 0); } },
    showFirstLabel: false
   }],
   legend: { align: 'left', verticalAlign: 'top', y: 20, floating: true, borderWidth: 0 },                                        
   tooltip: { shared: true, crosshairs: true },
   plotOptions: { series: { marker: { lineWidth: 1 } } }
  }
  
<?php

	// Preparing list of query IDs along with other information
	if($type == 'project'){
	 $results_cnt = $query_names = array();
	 $query_ids = $this->getQueryIds($id, $results_cnt, $query_names);
	}else{
	 $query_ids = (int)$id;
	 $results_cnt = array('facebook' => array(), 'twitter' => array());
	}
	
	// Declaring empty obkect for each series
	echo 'options.series = ['.str_repeat('{},', count($results_cnt)).'];'."\n";

	// Fetching "published" info for each entire of the query in given date range
 	$query = 'SELECT search_source `source`, search_published `date`, query_id
     	        FROM '.$prefix.'search
       	       WHERE query_id IN ('.$query_ids.')
          	GROUP BY search_id';
	$res = mysql_query($query);
 	 
	// Counting results by day and storing in $results_cnt array
	while($obj = mysql_fetch_object($res)){
	 $darr = getdate($obj->date);
	 $date = mktime(0, 0, 0, $darr['mon'], $darr['mday'], $darr['year']);
	 $results_cnt[$type == 'query' ? $obj->source : $obj->query_id][$date]++;
	 $min_date = isset($min_date) && $min_date < $date ? $min_date : $date;
	 $max_date = isset($max_date) && $max_date > $date ? $max_date : $date;
	}
	
	$query = 'SELECT * FROM '.$prefix.'search_index WHERE query_id IN ('.$query_ids.')';
	$res = mysql_query($query);

	while($obj = mysql_fetch_object($res)){
	 $results_cnt[$type == 'query' ? $obj->index_source : $obj->query_id][$obj->index_date] += $obj->index_count;
	 $min_date = isset($min_date) && $min_date < $obj->index_date ? $min_date : $obj->index_date;
	 $max_date = isset($max_date) && $max_date > $obj->index_date ? $max_date : $obj->index_date;
	}
	
	// Printing out JS code that fill each series data array
	$series_cnt = 0;
	while(list($key, $val) = each($results_cnt)){
	 echo 'options.series['.$series_cnt.'].data = [';
	 for($date = $min_date; $date <= $max_date; $date += 86400){
	  echo '['.($date*1000).', '.(int)$val[$date].']'.($date+86400 > $max_date ? '];'."\n" : ',');
	 }
	 $name = $type == 'query' ? $key : $query_names[$key];
	 echo 'options.series['.$series_cnt++.'].name = "'.$name.'";'."\n";
	 echo 'active_queries["'.$name.'"] = "'.$key.'";'."\n";
	}

?>
  Highcharts.setOptions({global:{useUTC:false}});
  chart = new Highcharts.StockChart(options);
  
<?php if(!isset($_COOKIE['msg_legend_hide'])) : ?>  
  jQuery('#container').prepend('<div id="message-container"><table class="popup" cellspacing="0" cellpadding="0"><tr><td id="topleft" class="corner"></td><td class="top"></td><td id="topright" class="corner"></td></tr><tr><td class="bg-left"></td><td class="popup-contents">Click on legend labels<br />to change graph view.<p class="hide-note">Click on this cloud to hide.</p></td><td class="bg-right"><img width="30" height="29" alt="popup tail" src="images/bubble-tail.png"/></td></tr><tr><td class="corner" id="bottomleft"></td><td class="bottom"></td><td id="bottomright" class="corner"></td></tr></tbody></table></div>');
  setTimeout(function () { jQuery('.popup').animate({ opacity:'toggle', left:'-=10px' }, 250, 'swing'); }, 3000);
  jQuery('.popup').live('click', function() {
   jQuery(this).animate({ opacity:'toggle', left:'+=10px' }, 250, 'swing');
   document.cookie = 'msg_legend_hide=1; expires=Thu, 1 Dec <?php echo date('Y') + 1 ?> 20:00:00 UTC; path=/'
  });
<?php endif; ?>

  });

</script>
<div id="container" style="width:650px; height:400px; margin:0 auto"></div>
<?php
  
  }
  
  /*
  *  Gets list of query IDs for project
  *
  *  @param int, mixed, mixed
  *  @return array
  */
  private function getQueryIds($project_id, &$results_cnt = NULL, &$query_names = NULL){
   global $prefix;

   $query = 'SELECT q.* 
	           FROM '.$prefix.'project_to_query p2q 
	 	 INNER JOIN '.$prefix.'query q ON p2q.query_id = q.query_id
	 	      WHERE p2q.project_id = '.(int)$project_id;
   $res = mysql_query($query);
   while($res && $query = mysql_fetch_object($res)){
	$query_ids[] = $query->query_id;
	if(is_array($results_cnt)){
	 $results_cnt[$query->query_id] = array();
	}
	if(is_array($query_names)){
	 $query_names[$query->query_id] = $query->query_q.($query->query_lang ? ' ('.$query->query_lang.')' : '');
	}
   }
   return implode(', ', $query_ids);
  }
  
  /*
  *  Prints search results wire
  *
  *  @param string, int
  *  @return void
  */
  private function wire($type, $id){

?>
<script type="text/javascript" src="js/jquery.jcarousel.min.js"></script>
<script type="text/javascript">

var theModelCarousel = null;
var active_queries = new Array;
var exclude_series = new Array;

function mycarousel_itemLoadCallback(carousel, state){
	theModelCarousel = carousel;

    jQuery.get(
        '<?php echo $this->getUrl('projects/wirexml') ?>',
        {
            ids: "<?php echo urlencode($type == 'project' ? $this->getQueryIds($id) : (int)$id) ?>",
            first: carousel.first,
            last: carousel.last,
            from: extrems.min/1000,
            to: extrems.max/1000
        },
        function(xml) {
			carousel.size(parseInt(jQuery('total', xml).text()));
    		jQuery('node', xml).each(function(i) {
        		carousel.add(carousel.first + i, '<div class="results-container' + ($('source', this).text() == 'facebook' ? ' facebook' : '') + '"><div class="left"><a href="' + $('link', this).text() + '" target="_blank" title="' + $('author', this).text() + '" onclick="blur();"><img src="' + $('image', this).text() + '" alt="' + $('author', this).text() + '" /></a></div><div class="left msg-text"><strong>' + $('author', this).text() + '</strong><div class="date">' + $('date', this).text() + '</div>' + $('content', this).text().replace(/&amp;/, '&') + '</div><div class="clear"></div></div>');
		 	});
        },
        'xml'
    );
};

jQuery(document).ready(function(){
    jQuery('#mycarousel').jcarousel({
	    vertical: true,
		scroll: 10,
        itemLoadCallback: mycarousel_itemLoadCallback
    });
});

</script>

  <h3>Raw Stream</h3>
  <div id="mycarousel" class="jcarousel-skin-tango"><ul><!-- The content will be dynamically loaded in here --></ul></div>

<?php

  }

  /*
  *  Displays top influencers
  *
  *  @param none
  *  @return void
  */
  function topInfluencers($type, $id){
?>
  
  <script type="text/javascript">
  
   function updateTopInfluencers(from, to){
    jQuery.get(
        '<?php echo $this->getUrl('projects/getinfluencers/') ?>',
        {
            ids: "<?php echo urlencode($type == 'project' ? $this->getQueryIds($id) : (int)$id) ?>",
			from: from,
			to: to
        },
        function(ret_html) {
		 jQuery('#top-influencers').html(ret_html);
        },
        'html'
    );
   }

  </script>
  
    <div id="top-influencers"><!-- The content will be dynamically loaded in here --></div>
<?php
  }
  
  /*
  *  Displays results page
  *
  *  @param string, int
  *  @return void
  */
  function resultsAction($type, $id){
   global $prefix;
   
   a_header('');
   
   // jQuery lib is declared here as it is required by graph, top influencers and wire
   echo '<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>';
   $this->graph($type, $id);
   $this->topInfluencers($type, $id);
   $this->wire($type, $id);
   a_footer();   
  }
  
  function aasort (&$array, $key) {
    $sorter = array();
    $ret = array();
    reset($array);
    foreach($array as $ii => $va) {
     $sorter[$ii] = $va[$key];
    }
    arsort($sorter);
    foreach($sorter as $ii => $va) {
     $ret[$ii] = $array[$ii];
    }
    $array = $ret;
  }

 } 

?>