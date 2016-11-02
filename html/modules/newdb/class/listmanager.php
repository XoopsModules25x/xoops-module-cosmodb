<?php

/**
 * class ListManager
 */

class ListManager{

	var $db;
	var $list_id;
	var $type;
	var $template;
	var $list_th;
	var $thumb_dir;
	var $thumb_size;
	var $thumb_active_size;
	var $sort_target;
	var $sort_method;
	var $page;
	var $limit;
	var $refine;
	var $refine_flg;
	var $kws_flg;
	var $uid;
	var $item;
	var $error;
	
	/**
	 * Class Constructor
	 */
	function ListManager(){
		$this->db =& Database::getInstance();
	}
	
	function setUser($time){
		$this->uid = $time;
	}
	
	function setListId($list_id){

		# get template	
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_list')." WHERE list_id='".$list_id."'";
		$rs = $this->db->query($sql);
	
		if($this->db->getRowsNum($rs) == 0){
			$this->error = 'This List ID does not exist. (listmanager.php line '.__LINE__.')';
			$this->list_id = -1;
			return false;
		
		}else{
			$row = $this->db->fetchArray($rs);
			$this->list_id = $list_id;
			$this->type = $row['type'];
			$this->template = $row['template'];
			$this->thumb_dir = $row['thumb_dir'];			
			$this->thumb_size = explode(';', $row['thumb_size']);
			$this->page = 1;
			$this->item = 0;
			$this->limit = 20;
			$this->refine = array();
			$this->refine_flg = 0;
			$this->kws_flg = 0;
			$this->list_th = $this->setListTH($row['list_th']);

			if($this->type == 2){
				$this->__setSize();
			}
		}
		return true;
	}
	
	function setListTH($th){
	
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_component_master');
		$rs = $this->db->query($sql);
		while($row = $this->db->fetchArray($rs)){
			$template = '{'.$row['name'].'}';
			if(strstr($th, $template)){
				$th = str_replace($template, $row['tag'], $th);
			}
		}
		return	$th; 
	}
	
	
	###########################
	## thumbnail things    
	###########################

	/**
	 * __setSize
	 *
	 * setting thumbnail with default size-set
	 * @access private
	 */
	function __setSize(){
	
		$size = array();
		$size = explode(',', $this->thumb_size[0]);
		
		$this->thumb_active_size[0] = $size[0];
		$this->thumb_active_size[1] = $size[1];
		$this->thumb_active_size[2] = $size[2];
		$this->thumb_active_size[3] = $size[3];
		
		return true;
	}
	
	
	/**
	 * changeSize
	 *
	 * change thumbnail size
	 * @access public
	 * @param $name (target thumbnail size name)
	 */
	function changeSize($name){

		$myts =& MyTextSanitizer::getInstance();
		$name = $myts->stripSlashesGPC($name);

		$size = array();
		for($i=0; $i<count($this->thumb_size); $i++){
			$size[] = explode(',', $this->thumb_size[$i]);
		}

		for($i=0; $i<count($this->thumb_size); $i++){
			if($size[$i][0] == $name){
				$this->thumb_active_size[0] = $size[$i][0];
				$this->thumb_active_size[1] = $size[$i][1];
				$this->thumb_active_size[2] = $size[$i][2]; 
				$this->thumb_active_size[3] = $size[$i][3];
			}
		}
		
		return true;
	}


	/**
	 * __getThumbLink
	 *
	 * making thumbnail size selection box
	 * @access private
	 * @return $thumb_link (thumbnail size selection box)
	 */
	function __getThumbLink(){

		$thumb_link = "<select name='size'>";
		for($i=0; $i<count($this->thumb_size); $i++){

			$ts = explode(',', $this->thumb_size[$i]);
			$thumb_link.= "<option value=".$ts[0];
			if($ts[0] == $this->thumb_active_size[0]){
				$thumb_link.= " selected";
			}
			$thumb_link.= ">".$ts[0]."</option> \n";
		}

		$thumb_link.= "</select>";
		return $thumb_link;
	}

	
	###########################
	## paging things    
	###########################

	/**
	 * setPage
	 *
	 * setting page number and limit
	 * @access public
	 * @param $p (page number)
	 * @param $l (showing limit)
	 */
	function setPage($item, $l){

		if($l < 0 || !$l) $l = 20;
		$this->limit = intval($l);

		if($item <= 0 || !$item){
			$p = 1;
			$this->item = 0;
		}else{
			$p = $item / $this->limit + 1;
			$this->item = $item;
		}
		$this->page = intval($p);
	}


	/**
	 * getPagelink
	 *
	 * making page selection link
	 * @access public
	 * @return $pagelink
	 */
	function getPagelink(){
		
		$n = count($this->getLabels(1));
		$pagenum = ceil($n / $this->limit);
		$pagenow = $this->page.'/'.$pagenum;
		$f = ($pagenow-1) * $this->limit + 1;
		$t = $pagenow * $this->limit;
		if($t > $n) $t = $n;
		$pagelink = _ND_CLASS_ALL.$n._ND_CLASS_HIT." ( ".$f." - ".$t._ND_CLASS_HIT_NOW." ) &nbsp;";

		$href = "id=".$this->list_id;
		$href.= "&n=".$this->limit;
		$href.= "&sort=".$this->sort_target;
		$href.= "&sort_method=".$this->sort_method;
		if($this->refine_flg){
			$href.= "&refine=usedb";
			$href.= "&user=".$this->uid;
		}
		if($this->type == 2) $href.= "&size=".$this->thumb_active_size[0];

		require XOOPS_ROOT_PATH.'/class/pagenav.php';
		$xp = new XoopsPageNav($n, $this->limit, $f, 'item', $href);
		$pagelink.= $xp->renderNav();

		return $pagelink;
	}
	
	
	###########################
	## sort things    
	###########################

	/**
	 * setSort
	 *
	 * setting sort target id and method
	 * @access public
	 * @param $sort_target (sort item's ID)
	 * @param $sort_method (asc or desc)
	 */
	function setSort($sort_target, $sort_method){
		$this->sort_target = intval($sort_target);
		if($sort_method != 'desc' && $sort_method != 'asc'){
			$this->sort_method = 'desc';
		}else{
			$this->sort_method = $sort_method;
		}
	}
	
	
	/**
	 * getSortbox
	 *
	 * making sort box for list and thumbnail page
	 * @access public
	 * @return $sortbox (sort box)
	 */
	function getSortbox(){

		$sortbox = "<form method='GET' action='list.php' style='margin:0'>\n";
		$sortbox.= "<select name='sort'>\n";
		
		# sort target
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_component_master');
		$sql.= " WHERE onoff='0' ORDER BY sort";
		$rs = $this->db->query($sql);
		while($row = $this->db->fetchArray($rs)){
			$sortbox.= "<option value='".$row['comp_id']."' ";
			if($row['comp_id'] == $this->sort_target){
				$sortbox.= "selected";
			}
			$sortbox.= ">".$row['tag']."</option>\n";
		}
		$sortbox.= "</select>\n";
		
		# up/down
		$sortbox.= "<select name='sort_method'>\n";
		$sortbox.= "<option value='asc' ";
		if($this->sort_method == 'asc'){
			$sortbox.= "selected";
		}
		$sortbox.= ">"._ND_UP."</option>\n";
		
		$sortbox.= "<option value='desc' ";
		if($this->sort_method == 'desc'){
			$sortbox.= "selected";
		}
		$sortbox.= ">"._ND_DOWN."</option>\n";
		$sortbox.= "</select>\n";
		
		# show limit
		$n = array(20,40,60,80,100);
		$sortbox.="<select name='n'>";
		for($i=0; $i<count($n); $i++){
			$sortbox.="<option vaule='".$n[$i]."'";
			if($this->limit == $n[$i]) $sortbox.= "selected";
			$sortbox.=">".$n[$i]."</option>";
		}
		$sortbox.="</select>";
		
		# thumb size
		if($this->type == 2){
			$sortbox.= $this->__getThumbLink();
		}

		$sortbox.= "<input type='hidden' name='id' value='".$this->list_id."'>\n";
		$sortbox.= "<input type='hidden' name='item' value='0'>\n";
		if($this->refine_flg){
			$sortbox.= "<input type='hidden' name='refine' value='usedb'>\n";
			$sortbox.= "<input type='hidden' name='user' value='".$this->uid."'>\n";
		}
		if($this->kws_flg){
			$sortbox.= "<input type='hidden' name='kws' value='paging'>\n";
		}
		$sortbox.= " <input type='submit' value='Sort' style='border:1px solid; background:white'>\n";
		$sortbox.= "</form>\n";
		
		return $sortbox;
	}
	
	
	###########################
	## refine things    
	###########################

	/**
	 * getRefinebox
	 *
	 * making refine box
	 * @access public
	 * @return $value (refine box)
	 */
	function getRefinebox(){
	
		# author
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_component_master');
		$sql.= " WHERE name='Author'";
		$rs = $this->db->query($sql);
		$row = $this->db->fetchArray($rs);
		$value= "<table class='list_table' style='width:80%;'>\n";
		$value.= "<tr><td style='width:15%'>".$row['tag']."</td><td>\n";
		$value.= "<select name='author[]' size='5' MULTIPLE>\n";
		
		$sql = "SELECT uid,uname FROM ".$this->db->prefix('users');
		$rs = $this->db->query($sql);
		while($row = $this->db->fetchArray($rs)){
			$value.="<option value='".$row['uid']."'>".$row['uname']."</option>\n";
		}
		$value.= "</select></td>\n";
		
		# date
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_component_master');
		$sql.= " WHERE name='Creation Date'";
		$rs = $this->db->query($sql);
		$row = $this->db->fetchArray($rs);
		$value.= "<td style='width:15%'>".$row['tag']."</td><td>\n";		

		$sql = "SELECT reg_date FROM ".$this->db->prefix('newdb_master')." ORDER BY reg_date asc";
		$rs = $this->db->query($sql);
		$year = array();
		while($row = $this->db->fetchArray($rs)){
			$d = date('Y', $row['reg_date']);
			if(!in_array($d, $year)) $year[] = $d;
		}
		
		for($i=1; $i<3; $i++){
			($i==1) ? $value.="From <br>" : $value.="<br>To <br>";
		
			$value.="<select name='year".$i."'>\n";
			for($j=0; $j<count($year); $j++){
				if($i==2 && $j==count($year)-1){
					$value.="<option value='".$year[$j]."' selected>".$year[$j]."</option>\n";
				}else{
					$value.="<option value='".$year[$j]."'>".$year[$j]."</option>\n";
				}
			}
			$value.="</select>\n<select name='month".$i."'>\n";
			for($j=1; $j<13; $j++){
				if($i==2 && $j==12){
					$value.="<option value='".$j."' selected>".$j."</option>\n";				
				}else{
					$value.="<option value='".$j."'>".$j."</option>\n";
				}
			}
			$value.="</select>\n<select name='day".$i."'>\n";
			for($j=1; $j<32; $j++){
				if($i==2 && $j==31){
					$value.="<option value='".$j."' selected>".$j."</option>\n";				
				}else{
					$value.="<option value='".$j."'>".$j."</option>\n";
				}
			}
			$value.="</select>\n";
		}
		$value.= "</td></tr></table>\n";
	
		# custom items ($type 3:checkbox, 2:radio)
		$value.= "<table class='list_table' style='width:80%; margin:10px 0 10px 0'>\n";
		for($i=2; $i<4; $i++){
			$sql = "SELECT * FROM ".$this->db->prefix('newdb_component_master');
			$sql.= " WHERE type='".$i."' ORDER BY onoff, sort";
			$rs = $this->db->query($sql);
			$type_id=0;
			while($row = $this->db->fetchArray($rs)){
				$value.= "<tr><td style='width:20%'>".htmlspecialchars($row['tag'])."</td><td>";
				$comp_id = $row['comp_id'];

				if($row['type'] == '2'){
					$svalue = explode(',', $row['select_value']);
					for($j=0; $j<count($svalue); $j++){
						$value.= "<input type='checkbox' name='CR".$type_id."[]' value='".$svalue[$j]."'>".$svalue[$j]."&nbsp;&nbsp;\n";
					}
					$value.= "<input type='hidden' name='CR".$type_id."_id' value='".$comp_id."'>";

				}elseif($row['type']=='3'){
					$svalue = explode(',', $row['select_value']);
					for($j=0; $j<count($svalue); $j++){
						$value.= "<input type='checkbox' name='CC".$type_id."[]' value='".$svalue[$j]."'>".$svalue[$j]."&nbsp;&nbsp;\n";
					}
					$value.= "<input type='hidden' name='CC".$type_id."_id' value='".$comp_id."'>";
				}
				$value.= "</td></tr>\n";
				$type_id++;
			}
		}
		$value.= "</table>\n";

		if($this->type == 2){
			$value.="<input type='hidden' name='size' value='".$this->thumb_active_size[0]."'>\n";	
		}
		$value.= "<input type='hidden' name='sort' value='".$this->sort_target."'>\n";	
		$value.= "<input type='hidden' name='sort_method' value='".$this->sort_method."'>\n";	
		$value.= "<input type='hidden' name='id' value='".$this->list_id."'>\n";		
		$value.= "<input type='hidden' name='n' value='".$this->limit."'>\n";
		$value.= "<input type='hidden' name='item' value='0'>\n";
		$value.= "<input type='hidden' name='refine' value='y'>\n";
		$value.= "<input type='hidden' name='user' value='".$this->uid."'>\n";
		if($this->refine_flg){
			$value.= "<input type='submit' name='more' value='"._ND_CLASS_REFINE2."' style='border:1px solid black; background:white'> ";
		}
		$value.= "<input type='submit' name='do' value='"._ND_CLASS_REFINE."' style='border:1px solid black; background:white'> ";
		$value.= "<input type='submit' name='all' value='"._ND_CLASS_SHOWALL."' style='border:1px solid black; background:white'> ";

		return $value;
	}
	
	
	/**
	 * setRefine
	 *
	 * setting refine option
	 * @access public
	 */
	function setRefine($author, $from, $to, $component, $mode){

		$this->refine_flg = 1;
		$authors = '';
		if(!empty($author)){
			$author = explode(',', $author);
			for($i=0; $i<count($author); $i++){
				if($authors) $authors.=' OR ';
				$authors.= "author='".$author[$i]."'";	
			}
			if($authors != '') $authors = "(".$authors.") AND ";
		}
		$date = "(reg_date >= '".$from."' AND reg_date <= '".$to."')";

		$tmp1 = array();	
		$sql = "SELECT label_id FROM ".$this->db->prefix('newdb_master')." WHERE ".$authors." ".$date;
		$rs = $this->db->query($sql);
		while($row = $this->db->fetchArray($rs)){
			$tmp1[] = $row['label_id'];
		}

		$tmp2 = array();
		for($i=0; $i<count($component); $i++){
			$sql = "SELECT label_id FROM ".$this->db->prefix('newdb_component');
			$sql.= " WHERE comp_id='".$component[$i][0]."' AND value='".$component[$i][1]."'";
			$rs = $this->db->query($sql);
			while($row = $this->db->fetchArray($rs)){
				if(!in_array($row['label_id'], $tmp2)) $tmp2[] = $row['label_id'];
			}
		}

		# check
		$this->refine = array();
		if(count($tmp2)){
			for($i=0; $i<count($tmp1); $i++){
				if(in_array($tmp1[$i], $tmp2)) $this->refine[] = $tmp1[$i];
			}
		}else{
			$this->refine = $tmp1;
		}
		
		# more (refine)
		if($mode == 1){
			$sql = "SELECT * FROM ".$this->db->prefix('newdb_list_refine')." WHERE user='".$this->uid."'";
			$rs = $this->db->query($sql);
			$row = $this->db->fetchArray($rs);
			$before = explode(',', $row['labels']);
			
			$new = array();
			for($i=0; $i<count($this->refine); $i++){
				if(in_array($this->refine[$i], $before)) $new[] = $this->refine[$i];
			}
			
			$this->refine = array();
			$this->refine = $new;
		}
		
		$tmp = '';
		for($i=0; $i<count($this->refine); $i++){
			if($tmp != '') $tmp.=',';
			$tmp .= $this->refine[$i];
		}
		
		if($mode == 1 || $mode == 0){
			$sql = "DELETE FROM ".$this->db->prefix('newdb_list_refine')." WHERE user='".$this->uid."'";
			$rs = $this->db->queryF($sql);
			
			$sql = "INSERT INTO ".$this->db->prefix('newdb_list_refine');
			$sql.= " VALUES('','".$this->uid."','".$tmp."')";
			$rs = $this->db->queryF($sql);
		}
		return $tmp;
	}
	
	
	function setRefineFromDB(){
	
		$this->refine_flg = 1;
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_list_refine')." WHERE user='".$this->uid."'";
		$rs = $this->db->query($sql);
		$row = $this->db->fetchArray($rs);
		$this->refine = explode(',', $row['labels']);
	}
	
	function getRefineFromDB(){

		$sql = "SELECT * FROM ".$this->db->prefix('newdb_list_refine')." WHERE user='".$this->uid."'";
		$rs = $this->db->query($sql);
		$row = $this->db->fetchArray($rs);
		
		$labels = explode(',', $row['labels']);
		$ret = array();
		for($i=0; $i<count($labels); $i++){
			if($labels[$i] != '') $ret[] = $labels[$i];
		}
		
		return $ret;
	}


	###########################
	## kws things    
	###########################

	function setKwsFlg(){
		$this->kws_flg = 1;
	}

	function setKLabels($kw, $andor, $notkws){
		if($andor != 'and' && $andor != 'or') $andor = 'and';
		
		# not keywords
		$notkws = explode(',', substr($notkws, 0, -1));
		$notnum = array();
		$notkw = '';
		for($i=0; $i<count($notkws); $i++){
			if(!isset($notnum[$notkws[$i]])) $notnum[$notkws[$i]] = 0;
			$notnum[$notkws[$i]]++;
		}
		foreach($notnum as $k => $v){
			if(($v % 2)){
				if(!empty($notkw)) $notkw.=',';
				$notkw.= $k;
			}
		}
		
		# save keyword
		if($kw){
			$sql = "INSERT INTO ".$this->db->prefix('newdb_list_refine_option');
			$sql.= " VALUES('', '".$this->uid."', '".$andor.":".$kw."')";
			$rs = $this->db->queryF($sql);
		}
		if($notkw){
			$sql = "INSERT INTO ".$this->db->prefix('newdb_list_refine_option');
			$sql.= " VALUES('', '".$this->uid."', 'not:".$notkw."')";
			$rs = $this->db->queryF($sql);
		}
		
		# kw search
		$kw = explode(',', $kw);
		$notkw = explode(',', $notkw);
		
		$kws = '';	$labels = array();
		for($i=0; $i<count($kw); $i++){
			if(empty($kw[$i])) continue;
			if(!empty($kws)) $kws.= ' '.$andor.' ';
			$kws.= "keyword like '%[".$kw[$i]."]%'";
		}
		
		$notkws = '';
		for($i=0; $i<count($notkw); $i++){
			if(empty($notkw[$i])) continue;
			if(!empty($notkws)) $notkws.= ' AND ';
			$notkws.= "keyword not like '%[".$notkw[$i]."]%'";
		}
		
		if($kws && $notkws){
			$kws = "(".$kws.") AND (".$notkws.")"; 
		}elseif(!$kws && $notkws){
			$kws = $notkws;
		}
		if($kws) $kws = " WHERE ".$kws;
		
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_master').$kws;
		$rs = $this->db->query($sql);
		$num = $this->db->getRowsNum($rs);
		if($num > 0){
			while($row = $this->db->fetchArray($rs)){
				$labels[] = $row['label_id'];
			}
		}
		#echo $sql;
		
		# check refine list
		$refines = $this->getRefineFromDB();
		$label_list = '';
		if(count($refines) > 0){
			for($i=0; $i<count($labels); $i++){
				if(in_array($labels[$i], $refines)){
					if(!empty($label_list)) $label_list.=',';
					$label_list.= $labels[$i];
				}
			}
		}else{
			for($i=0; $i<count($labels); $i++){
				if(!empty($label_list)) $label_list.=',';
				$label_list.= $labels[$i];
			}
		}
		
		$sql = "DELETE FROM ".$this->db->prefix('newdb_list_refine');
		$sql.= " WHERE user='".$this->uid."'";
		$rs = $this->db->queryF($sql);

		$sql = "INSERT INTO ".$this->db->prefix('newdb_list_refine');
		$sql.= " VALUES('', '".$this->uid."', '".$label_list."')";
		$rs = $this->db->queryF($sql);
		$this->setRefineFromDB();
	}
	

	###########################
	## general things    
	###########################

	function __getAllLabels(){
	
		$label_list = array();
		$sql = "SELECT name,type FROM ".$this->db->prefix('newdb_component_master');
		$sql.= " WHERE comp_id='".$this->sort_target."'";
		$rs = $this->db->query($sql);

		if($this->db->getRowsNum($rs) > 0){
			$row = $this->db->fetchArray($rs);

			# sort by system component
			if($row['type'] == 1){

				$sql = "SELECT label_id FROM ".$this->db->prefix('newdb_master')." ORDER BY ";
				if($row['name'] == 'ID'){
					$sql.= "label_id";
				}elseif($row['name'] == 'Data Name'){
					$sql.= "label";				
				}elseif($row['name'] == 'Author'){
					$sql.= "author";
				}elseif($row['name'] == 'Creation Date'){
					$sql.= "reg_date";				
				}elseif($row['name'] == 'Views'){
					$sql.= "views";
				}
				$sql.= " ".$this->sort_method;
				$rs = $this->db->query($sql);
				while($row = $this->db->fetchArray($rs)){
					$label_list[] = $row['label_id'];
				}

			# sort by radio, check, text component
			}else{

				$sql = "SELECT label_id FROM ".$this->db->prefix('newdb_component');
				$sql.= " WHERE comp_id='".$this->sort_target."' ORDER BY value ".$this->sort_method."";
				$rs = $this->db->query($sql);
				while($row = $this->db->fetchArray($rs)){
					if(!in_array($row['label_id'], $label_list)) $label_list[] = $row['label_id'];
				}
					
				$sql = "SELECT label_id FROM ".$this->db->prefix('newdb_master')." ORDER BY label_id desc";
				$rs = $this->db->query($sql);
				while($row = $this->db->fetchArray($rs)){
					if(!in_array($row['label_id'], $label_list)) $label_list[] = $row['label_id'];
				}			
			}		
		}else{
			$this->error = 'No item found. (listmanager.php line '.__LINE__.')';
		}

		return	$label_list;
	}

	/**
	 * getLabels
	 *
	 * making label list for show
	 * @access public
	 * @return $return_list (label array)
	 */
	function getLabels($mode=0){

		$label_list = $this->__getAllLabels();

		#refine
		if($this->refine_flg){
			$label_list2 = array();
			for($i=0; $i<count($label_list); $i++){
				if(in_array($label_list[$i], $this->refine)) 
					$label_list2[] = $label_list[$i];
			}
			$label_list = array();
			for($i=0; $i<count($label_list2); $i++)
				$label_list[] = $label_list2[$i];
		}

		# paging
		$return_list = array();
		$start = ($this->page - 1) * $this->limit;
		for($i=$start; $i<$start+$this->limit; $i++){
			if(count($label_list) <= $i) break;
			$return_list[] = $label_list[$i];
		}
		
		# return refine labels for paging
		if($mode){
			$return_list = $label_list;
		}
	
		return $return_list;
	}

	/**
	 * getValues
	 *
	 * replace template like {ID} into value
	 * @access public
	 * @param $label_id (label id)
	 * @param $label (label name)
	 * @param $author (author)
	 * @param $date (creation date)
	 * @return $template
	 */
	function getValues($label_id, $label, $author, $date, $views, $dname_flg=0){

		# uname
		$sql = "SELECT uname FROM ".$this->db->prefix('users')." WHERE uid='".$author."'";
		$rs = $this->db->query($sql);
		$row = $this->db->fetchArray($rs);
		$uname = $row['uname'];
		if($dname_flg){
			$label = "<a href='detail.php?id=".$label_id."'>".$label."</a>";
			$label_id4show = $label_id;
		}else{
			$label_id4show = "<a href='detail.php?id=".$label_id."'>".$label_id."</a>";
		}
		$template = $this->template;
		
		if(strstr($template, '{ID}')){
			$template = str_replace('{ID}', $label_id4show, $template);
		}
		if(strstr($template, '{Data Name}')){
			$template = str_replace('{Data Name}', $label, $template);
		}
		if(strstr($template, '{Author}')){
			$template = str_replace('{Author}', $uname, $template);
		}
		if(strstr($template, '{Creation Date}')){
			$template = str_replace('{Creation Date}', date('Y-m-d', $date), $template);
		}
		if(strstr($template, '{Views}')){
			$template = str_replace('{Views}', $views, $template);
		}
	
		# get component value
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_component_master');
		$rs = $this->db->query($sql);
		while($row = $this->db->fetchArray($rs)){

			if(strstr($template,'{'.$row['name'].'}')){
				$sql = "SELECT * FROM ".$this->db->prefix('newdb_component')." WHERE label_id='".$label_id."' AND comp_id='".$row['comp_id']."'";
				$rs2 = $this->db->query($sql);
				$value = '';
				while($row2 = $this->db->fetchArray($rs2)){
					if($value) $value.= ', ';
					$value.= $row2['value'];
				}
				$template = str_replace('{'.$row['name'].'}', $value, $template);
			}
		}
		
		# clipboard
		if(strstr($template, '{Ref ')){
			$st = 0; $end = 0;
			for(;;){
				$st = strpos($template, '{Ref ', $end);
				if($st > $end){
					$end = strpos($template, '}', $st);
					$ref = substr($template, $st, ($end - $st + 1));
					$suffix = str_replace('{Ref ', '', $ref);
					$suffix = str_replace('}', '', $suffix);
					
					$path='';
					$sql = "SELECT * FROM ".$this->db->prefix('newdb_master')." WHERE label_id='".$label_id."'";
					$rs = $this->db->query($sql);
					$row = $this->db->fetchArray($rs);
					$label = $row['label'];
					
					$sql = "SELECT * FROM ".$this->db->prefix('newdb_item');
					$sql.= " WHERE label_id='".$label_id."' AND name like '%.".$suffix."' AND type='file'";
					$rs = $this->db->query($sql);
					if($this->db->getRowsNum($rs)){
						while($row = $this->db->fetchArray($rs)){
							if(!empty($row['path'])){
								$p = $row['path']."/";
							}else{
								$p = '';
							}
							$tmp = "extract/".$label_id."/data/".$p.$row['name'];
							$path.= "<a style='cursor: pointer;' onClick=\"javascript:setClipboard('".$tmp."')\">".$row['name']."</a><br>";
						}
					}else{
						$path = '';
					}
					$template = str_replace($ref, $path, $template);
				}else{
					break;
				}
			}
		}
		
		# directories
		if(strstr($template, '{Dirs}')){
			$sql = "SELECT * FROM ".$this->db->prefix('newdb_item');
			$sql.= " WHERE type='dir' AND path='' AND label_id='".$label_id."' ORDER BY name";
			$rs = $this->db->query($sql);
			$dirs = '';
			while($row = $this->db->fetchArray($rs)){
				if(!empty($dirs)) $dirs.=', ';
				$dirs.= substr($row['name'], 0, 3);
			}
			$template = str_replace('{Dirs}', $dirs, $template);
		}
		
		return $template;
	}

	function getListlink(){
		
		$ret_value = '';
		$sql = "SELECT * FROM ".$this->db->prefix('newdb_list')." WHERE onoff='0' ORDER BY sort";
		$rs = $this->db->query($sql);
		while($row = $this->db->fetchArray($rs)){
			$list_id = $row['list_id'];
			$name = $row['name'];
			
			$href = "id=".$list_id;
			$href.= "&n=".$this->limit;
			$href.= "&sort=".$this->sort_target;
			$href.= "&sort_method=".$this->sort_method;
			if($this->refine_flg){
				$href.= "&refine=usedb";
				$href.= "&user=".$this->uid;
			}
			if($this->type == 2) $href.= "&size=".$this->thumb_active_size[0];
			$href.= "&item=".$this->item;
			
			if(!empty($ret_value)) $ret_value.= ' | ';
			if($list_id == $this->list_id){
				$ret_value.= $name;
			}else{
				$ret_value.= "<a href='list.php?".$href."'>".$name."</a>";
			}
		}
		return $ret_value;
	}
	
	function error(){
		return $this->error;
	}
	
}
?>