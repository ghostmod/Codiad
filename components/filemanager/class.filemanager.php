<?php

/*
*  Copyright (c) Codiad & Kent Safranski (codiad.com), distributed
*  as-is and without warranty under the MIT License. See 
*  [root]/license.txt for more. This information must remain intact.
*/

class Filemanager {

    //////////////////////////////////////////////////////////////////
    // PROPERTIES
    //////////////////////////////////////////////////////////////////
    
    public $root          = "";
    public $project       = "";
    public $rel_path      = "";
    public $path          = "";
    public $type          = "";
    public $new_name      = "";
    public $content       = "";
    public $destination   = "";
    public $upload        = "";
    public $controller    = "";
    public $upload_json   = "";
    public $search_string = "";
    
    // JSEND Return Contents
    public $status        = "";
    public $data          = "";
    public $message       = "";
    
    //////////////////////////////////////////////////////////////////
    // METHODS
    //////////////////////////////////////////////////////////////////
    
    // -----------------------------||----------------------------- //
        
    //////////////////////////////////////////////////////////////////
    // Construct
    //////////////////////////////////////////////////////////////////
    
    public function __construct($get,$post,$files) {
        $this->rel_path = $get['path'];
        if($this->rel_path!="/"){ $this->rel_path .= "/"; } 
        $this->root = $get['root'];
        $this->path = $this->root . $get['path'];
        // Search
        if(!empty($post['search_string'])){ $this->search_string = $post['search_string']; }
        // Create
        if(!empty($get['type'])){ $this->type = $get['type']; }
        // Modify\Create
        if(!empty($get['new_name'])){ $this->new_name = $get['new_name']; }
        if(!empty($post['content'])){ 
            if(get_magic_quotes_gpc()){
                $this->content = stripslashes($post['content']); 
            }else{
                $this->content = $post['content'];
            }
        }
        // Duplicate
        if(!empty($get['destination'])){ $this->destination = $this->root . $get['destination']; }
    }

    //////////////////////////////////////////////////////////////////
    // INDEX (Returns list of files and directories)
    //////////////////////////////////////////////////////////////////
        
    public function index(){
    
        if(file_exists($this->path)){
            $index = array();
            if(is_dir($this->path) && $handle = opendir($this->path)){
                while (false !== ($object = readdir($handle))) {
                    if ($object != "." && $object != ".." && $object != $this->controller) {
                        if(is_dir($this->path.'/'.$object)){ $type = "directory"; $size=0; }
                        else{ $type = "file"; $size=filesize($this->path.'/'.$object); }
                        $index[] = array(
                            "name"=>$this->rel_path . $object,
                            "type"=>$type,
                            "size"=>$size
                        );
                    }
                }
                
                $folders = array();
                $files = array();
                foreach($index as $item=>$data){
                    if($data['type']=='directory'){
                        $folders[] = array("name"=>$data['name'],"type"=>$data['type'],"size"=>$data['type']);
                    }
                    if($data['type']=='file'){
                        $files[] = array("name"=>$data['name'],"type"=>$data['type'],"size"=>$data['type']);
                    }
                }
                
                function sorter($a, $b, $key = 'name') { return strnatcmp($a[$key], $b[$key]); }
                
                usort($folders,"sorter");
                usort($files,"sorter");
                
                $output = array_merge($folders,$files);
                
                $this->status = "success";
                $this->data = '"index":' . json_encode($output);
            }else{
                $this->status = "error";
                $this->message = "Not A Directory";
            }
        }else{
            $this->status = "error";
            $this->message = "Path Does Not Exist";
        }
            
        $this->respond();
    }
    
    //////////////////////////////////////////////////////////////////
    // SEARCH
    //////////////////////////////////////////////////////////////////
    
    public function search(){
        if(!function_exists('shell_exec')){
            $this->status = "error";
            $this->message = "Shell_exec() Command Not Enabled.";
        }else{
            chdir(WORKSPACE);
            if($this->path[0] == "/"){
                $path = substr($this->path,1);
            }else{
                $path = $this->path;
            }
            $input = str_replace('"' , '', $this->search_string);
            $input = preg_quote($input);
            $output = shell_exec('grep -i -I -n -R "' . $input . '" /' . $path . '/* ');
            $output_arr = explode("\n", $output);
            $return = array();
            foreach($output_arr as $line){
                $data = explode(":", $line);
                $da = array();
                if(count($data) > 2){
                    $da['line'] = $data[1];
                    $da['file'] = str_replace(WORKSPACE,'',$data[0]);
                    $da['string'] = str_replace($data[0] . ":" . $data[1] . ':' , '', $line);
                    $return[] = $da;
                }
            }
            if(count($return)==0){
                $this->status = "error";
                $this->message = "No Results Returned";
            }else{
                $this->status = "success";
                $this->data = '"index":' . json_encode($return);
            }
        }
        $this->respond();
    }
        
    //////////////////////////////////////////////////////////////////
    // OPEN (Returns the contents of a file)
    //////////////////////////////////////////////////////////////////
        
    public function open(){
        if(is_file($this->path)){
            $this->status = "success";
            $this->data = '"content":' . json_encode(file_get_contents($this->path));
        }else{
            $this->status = "error";
            $this->message = "Not A File :".$this->path;
        }

        $this->respond();
    }
    
    //////////////////////////////////////////////////////////////////
    // OPEN IN BROWSER (Return URL)
    //////////////////////////////////////////////////////////////////
    
    public function openinbrowser(){
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        $url =  $protocol.WSURL.$this->rel_path;
        $this->status = "success";
        $this->data = '"url":' . json_encode(rtrim($url,"/"));
        $this->respond();
    }
    
    //////////////////////////////////////////////////////////////////
    // CREATE (Creates a new file or directory)
    //////////////////////////////////////////////////////////////////
        
    public function create(){
    
        // Create file
        if($this->type=="file"){
            if(!file_exists($this->path)){
                if($file = fopen($this->path, 'w')){
                    // Write content
                    if($this->content){ fwrite($file, $this->content); }
                    fclose($file);
                    $this->status = "success";
                }else{
                    $this->status = "error";
                    $this->message = "Cannot Create File";
                }
            }else{
                $this->status = "error";
                $this->message = "File Already Exists";
            }
        }
        
        // Create directory
        if($this->type=="directory"){
            if(!is_dir($this->path)){
                mkdir($this->path);
                $this->status = "success";
            }else{
                $this->status = "error";
                $this->message = "Directory Already Exists";
            }
        }
    
        $this->respond();        
    }
        
    //////////////////////////////////////////////////////////////////
    // DELETE (Deletes a file or directory (+contents))
    //////////////////////////////////////////////////////////////////
        
    public function delete(){
    
        function rrmdir($path){
            return is_file($path)?
            @unlink($path):
            @array_map('rrmdir',glob($path.'/*'))==@rmdir($path);
        }
        
        if(file_exists($this->path)){ rrmdir($this->path); 
            $this->status = "success"; 
        }else{ 
            $this->status = "error";
            $this->message = "Path Does Not Exist";
        }
        
        $this->respond();
    }
        
    //////////////////////////////////////////////////////////////////
    // MODIFY (Modifies a file name/contents or directory name)
    //////////////////////////////////////////////////////////////////
    
    public function modify(){
    
        // Change name
        if($this->new_name){
            $explode = explode('/',$this->path);
            array_pop($explode);
            $new_path = "/" . implode("/",$explode) . "/" . $this->new_name;
            if(!file_exists($new_path)){
                if(rename($this->path,$new_path)){
                    //unlink($this->path);
                    $this->status = "success";
                }else{
                    $this->status = "error";
                    $this->message = "Could Not Rename";
                }
            }else{
                $this->status = "error";
                $this->message = "Path Already Exists";
            }
        }
        
        // Change content
        if($this->content){
            if($this->content==' '){ $this->content=''; } // Blank out file
            if(is_file($this->path)){
                if($file = fopen($this->path, 'w')){            
                    fwrite($file, $this->content);
                    fclose($file);
                    $this->status = "success";
                }else{
                   $this->status = "error";
                    $this->message = "Cannot Write to File";
                }
            }else{
                $this->status = "error";
                $this->message = "Not A File";
            }
        }
        
        $this->respond();        
    }
        
    //////////////////////////////////////////////////////////////////
    // DUPLICATE (Creates a duplicate of the object - (cut/copy/paste)
    //////////////////////////////////////////////////////////////////
    
    public function duplicate(){
        
        if(!file_exists($this->path)){ 
            $this->status = "error";
            $this->message = "Invalid Source";
        }
        
        function recurse_copy($src,$dst) { 
            $dir = opendir($src); 
            @mkdir($dst); 
            while(false !== ( $file = readdir($dir)) ) { 
                if (( $file != '.' ) && ( $file != '..' )) { 
                    if ( is_dir($src . '/' . $file) ) { 
                        recurse_copy($src . '/' . $file,$dst . '/' . $file); 
                    } 
                    else { 
                        copy($src . '/' . $file,$dst . '/' . $file); 
                    } 
                } 
            } 
            closedir($dir); 
        }
        
        if($this->status!="error"){
        
            if(is_file($this->path)){
                copy($this->path,$this->destination);
                $this->status = "success";
            }else{
                recurse_copy($this->path,$this->destination);
                if(!$this->response){ $this->status = "success"; }
            }
            
        }

        $this->respond();
    }
    
    //////////////////////////////////////////////////////////////////
    // UPLOAD (Handles uploads to the specified directory)
    //////////////////////////////////////////////////////////////////
    
    public function upload(){
    
        // Check that the path is a directory
        if(is_file($this->path)){ 
            $this->status = "error";
            $this->message = "Path Not A Directory";
        }else{
            // Handle upload
            $info = array();
            while(list($key,$value) = each($_FILES['upload']['name'])){
                if(!empty($value)){
                    $filename = $value;
                    $add = $this->path."/$filename";
                    if(@move_uploaded_file($_FILES['upload']['tmp_name'][$key], $add)){
                        
                        $info[] = array(
                            "name"=>$value,
                            "size"=>filesize($add),
                            "url"=>$add,
                            "thumbnail_url"=>$add,
                            "delete_url"=>$add,
                            "delete_type"=>"DELETE"
                        );
                    }
                }
            }
            $this->upload_json = json_encode($info);       
        }

        $this->respond();        
    }
        
    //////////////////////////////////////////////////////////////////
    // RESPOND (Outputs data in JSON [JSEND] format)
    //////////////////////////////////////////////////////////////////
    
    public function respond(){ 
        
        // Success ///////////////////////////////////////////////
        if($this->status=="success"){
            if($this->data){
                $json = '{"status":"success","data":{'.$this->data.'}}';
            }else{
                $json = '{"status":"success","data":null}';
            }
        
        // Upload JSON ///////////////////////////////////////////
        
        }elseif($this->upload_json!=''){
            $json = $this->upload_json;
        
        // Error /////////////////////////////////////////////////
        }else{
            $json = '{"status":"error","message":"'.$this->message.'"}';
        }
        
        // Output ////////////////////////////////////////////////
        echo($json); 
        
    }
    
}
    
?>
