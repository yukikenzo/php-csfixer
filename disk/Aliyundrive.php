<?php

class Aliyundrive {
    protected $access_token;
    protected $disktag;

    function __construct($tag) {
        $this->disktag = $tag;
        $this->auth_url = 'https://websv.aliyundrive.com/token/refresh';
        $this->api_url = 'https://api.aliyundrive.com/v2';
        $this->default_drive_id = getConfig('default_drive_id', $tag);
        $res = $this->get_access_token(getConfig('refresh_token', $tag));
    }
    
    public function isfine()
    {
        if (!$this->access_token) return false;
        else return true;
    }
    public function show_base_class()
    {
        return get_class();
        //$tmp[0] = get_class();
        //$tmp[1] = get_class($this);
        //return $tmp;
    }

    public function ext_show_innerenv()
    {
        return [];
    }

    public function list_files($path = '/')
    {
        


        $files = $this->list_path($path);


        
        return $this->files_format($files);
    }

    protected function files_format($files)
    {
        //return $files;
        if ($files['type']=='file') {
            $tmp['type'] = 'file';
            $tmp['id'] = $files['file_id'];
            if (isset($files['name'])) $tmp['name'] = $files['name'];
            elseif (isset($files['file_name'])) $tmp['name'] = $files['file_name'];
            $tmp['time'] = $files['updated_at'];
            $tmp['size'] = $files['size'];
            $tmp['mime'] = $files['file']['mimeType'];
            $tmp['url'] = $files['download_url'];
            $tmp['content'] = $files['content'];
            if (isset($files['exist'])) $tmp['exist'] = $files['exist'];
            if (isset($files['rapid_upload'])) $tmp['rapid_upload'] = $files['rapid_upload'];
        } elseif ($files['type']=='folder'||isset($files['items'])) {
            $tmp['type'] = 'folder';
            $tmp['id'] = $files['file_id'];
            if (isset($files['name'])) $tmp['name'] = $files['name'];
            elseif (isset($files['file_name'])) $tmp['name'] = $files['file_name'];
            $tmp['time'] = $files['updated_at'];
            $tmp['size'] = $files['size'];
            //$tmp['page'] = $files['folder']['page'];
            foreach ($files['items'] as $file) {
                $filename = strtolower($file['name']);
                if ($file['type']=='file') {
                    $tmp['list'][$filename]['type'] = 'file';
                    $tmp['list'][$filename]['url'] = $file['download_url'];
                    $tmp['list'][$filename]['mime'] = $file['file']['content_type'];
                } elseif ($file['type']=='folder') {
                    $tmp['list'][$filename]['type'] = 'folder';
                }
                //$tmp['id'] = $file['parent_file_id'];
                $tmp['list'][$filename]['id'] = $file['file_id'];
                $tmp['list'][$filename]['name'] = $file['name'];
                $tmp['list'][$filename]['time'] = $file['updated_at'];
                $tmp['list'][$filename]['size'] = $file['size'];
                $tmp['childcount']++;
            }
        } elseif (isset($files['code'])||isset($files['error'])) {
            return $files;
        }
        //error_log1(json_encode($tmp));
        return $tmp;
    }

    protected function list_path($path = '/')
    {
        global $exts;
        while (substr($path, -1)=='/') $path = substr($path, 0, -1);
        if ($path == '') $path = '/';
        //$files = getcache('path_' . $path, $this->disktag);
        //if (!$files) {
        if (!($files = getcache('path_' . $path, $this->disktag))) {
            if ($path == '/' || $path == '') {
                $files = $this->fileList('root');
                //error_log1('root_id' . $files['id']);
                $files['file_id'] = 'root';
                $files['type'] = 'folder';
            } else {
                $tmp = splitlast($path, '/');
                $parent_path = $tmp[0];
                $filename = urldecode($tmp[1]);
                $parent_folder = $this->list_path($parent_path);
                foreach ($parent_folder['items'] as $item) {
                    if ($item['name']==$filename) {
                        if ($item['type']=='folder') {
                            $files = $this->fileList($item['file_id']);
                            $files['type'] = 'folder';
                            $files['file_id'] = $item['file_id'];
                            $files['name'] = $item['name'];
                            $files['time'] = $item['updated_at'];
                            $files['size'] = $item['size'];
                        } else $files = $item;
                        
                    }
                    
                }
                //echo $files['name'];
            }
            if ($files['type']=='file') {
                if (in_array(splitlast($files['name'],'.')[1], $exts['txt'])) {
                    if (!(isset($files['content'])&&$files['content']['stat']==200)) {
                        $content1 = curl('GET', $files['download_url']);
                        $files['content'] = $content1;
                        savecache('path_' . $path, $files, $this->disktag);
                    }
                }
            }
            if (!$files) {
                $files['error']['code'] = 'Not Found';
                $files['error']['message'] = 'Not Found';
                $files['error']['stat'] = 404;
            } elseif (isset($files['stat'])) {
                $files['error']['stat'] = $files['stat'];
                $files['error']['code'] = 'Error';
                $files['error']['message'] = $files['body'];
            } else {
                savecache('path_' . $path, $files, $this->disktag, 600);
            }
        }
        //error_log1('path:' . $path . ', files:' . substr(json_encode($files), 0, 150));
        return $files;
    }

    protected function fileGet($file_id)
    {
        $url = $this->api_url . '/file/get';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['drive_id'] = $this->default_drive_id;
        $data['file_id'] = $file_id;

        $res = curl('POST', $url, json_encode($data), $header);
        if ($res['stat']==200) return json_decode($res['body'], true);
        else return $res;
    }
    protected function fileList($parent_file_id)
    {
        $url = $this->api_url . '/file/list';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['limit'] = 50;
        $data['marker'] = NULL;
        $data['drive_id'] = $this->default_drive_id;
        $data['parent_file_id'] = $parent_file_id;
        $data['image_thumbnail_process'] = 'image/resize,w_160/format,jpeg';
        $data['image_url_process'] = 'image/resize,w_1920/format,jpeg';
        $data['video_thumbnail_process'] = 'video/snapshot,t_0,f_jpg,w_300';
        $data['fields'] = '*';
        $data['order_by'] = 'updated_at';
        $data['order_direction'] = 'DESC';

        $res = curl('POST', $url, json_encode($data), $header);
        if ($res['stat']==200) return json_decode($res['body'], true);
        else return $res;
    }

    public function Rename($file, $newname) {
        $url = $this->api_url . '/file/update';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['check_name_mode'] = 'refuse';
        $data['drive_id'] = $this->default_drive_id;
        $data['file_id'] = $file['id'];
        $data['name'] = $newname;
        //$data['parent_file_id'] = 'root';

        $result = curl('POST', $url, json_encode($data), $header);
        //savecache('path_' . $file['path'], json_decode('{}',true), $this->disktag, 1);
        //error_log1('decode:' . json_encode($result));
        return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
        //return output($result['body'], $result['stat']);
    }
    public function Delete($file) {
        $url = $this->api_url . '/batch';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['resource'] = 'file';
        $data['requests'][0]['url'] = '/file/delete';
        $data['requests'][0]['method'] = 'DELETE';
        $data['requests'][0]['id'] = $file['id'];
        $data['requests'][0]['headers']['Content-Type'] = 'application/json';
        $data['requests'][0]['body']['drive_id'] = $this->default_drive_id;
        $data['requests'][0]['body']['file_id'] = $file['id'];

        $result = curl('POST', $url, json_encode($data), $header);
        //savecache('path_' . $file['path'], json_decode('{}',true), $this->disktag, 1);
        //error_log1('result:' . json_encode($result));
        //return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
        $res = json_decode($result['body'], true)['responses'][0];
        if (isset($res['status'])) return output($res['id'], $res['status']);
        else return output($result['body'], $result['stat']);
    }
    public function Encrypt($folder, $passfilename, $pass) {
        $existfile = $this->list_path($folder['path'] . '/' . $passfilename);
        if (isset($existfile['type'])) { // 删掉原文件
            $this->Delete(['id'=>$existfile['file_id']]);
        }
        if ($pass==='') {
            // 如果为空，上面已经删除了
            return output('Success', 200);
        }
        if (!$folder['id']) {
            $res = $this->list_path($folder['path']);
            //error_log1('res:' . json_encode($res));
            $folder['id'] = $res['file_id'];
        }
        $tmp = '/tmp/' . $passfilename;
        file_put_contents($tmp, $pass);

        $result = $this->tmpfileCreate($folder['id'], $tmp, $passfilename);

        if ($result['stat']==201) {
            //error_log1('1,url:' . $url .' res:' . json_encode($result));
            $res = json_decode($result['body'], true);
            $url = $res['part_info_list'][0]['upload_url'];
            if (!$url) { // 无url，应该算秒传
                return output('no up url', 200);
            }
            $file_id = $res['file_id'];
            $upload_id = $res['upload_id'];
            $result = curl('PUT', $url, $pass, [], 1);
            if ($result['stat']==200) { // 块1传好
                $tmp1['part_number'] = 1;
                $tmp1['etag'] = $result['returnhead']['ETag'];
                $result = $this->fileComplete($file_id, $upload_id, [ $tmp1 ]);
                return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
            }
        }
        //error_log1('2,url:' . $url .' res:' . json_encode($result));
        return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
    }
    public function Move($file, $folder) {
        if (!$folder['id']) {
            $res = $this->list_path($folder['path']);
            //error_log1('res:' . json_encode($res));
            $folder['id'] = $res['file_id'];
        }

        $url = $this->api_url . '/batch';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['resource'] = 'file';
        $data['requests'][0]['url'] = '/file/move';
        $data['requests'][0]['method'] = 'POST';
        $data['requests'][0]['id'] = $file['id'];
        $data['requests'][0]['headers']['Content-Type'] = 'application/json';
        $data['requests'][0]['body']['drive_id'] = $this->default_drive_id;
        $data['requests'][0]['body']['file_id'] = $file['id'];
        $data['requests'][0]['body']['auto_rename'] = true;
        $data['requests'][0]['body']['to_parent_file_id'] = $folder['id'];

        $result = curl('POST', $url, json_encode($data), $header);
        //savecache('path_' . $file['path'], json_decode('{}',true), $this->disktag, 1);
        //error_log1('result:' . json_encode($result));
        return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
    }
    public function Copy($file) {
        if (!$file['id']) {
            $oldfile = $this->list_path($file['path'] . '/' . $file['name']);
            //error_log1('res:' . json_encode($res));
            //$file['id'] = $res['file_id'];
        } else {
            $oldfile = $this->fileGet($file['id']);
        }

        $url = $this->api_url . '/file/create';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['check_name_mode'] = 'auto_rename'; // ignore, auto_rename, refuse.
        $data['content_hash'] = $oldfile['content_hash'];
        $data['content_hash_name'] = 'sha1';
        $data['content_type'] = $oldfile['content_type'];
        $data['drive_id'] = $this->default_drive_id;
        $data['ignoreError'] = false;
        $data['name'] = $oldfile['name'];
        $data['parent_file_id'] = $oldfile['parent_file_id'];
        $data['part_info_list'][0]['part_number'] = 1;
        $data['size'] = $oldfile['size'];
        $data['type'] = 'file';

        $result = curl('POST', $url, json_encode($data), $header);

        if ($result['stat']==201) {
            //error_log1('1,url:' . $url .' res:' . json_encode($result));
            $res = json_decode($result['body'], true);
            $url = $res['part_info_list'][0]['upload_url'];
            if (!$url) { // 无url，应该算秒传
                return output('no up url', 200);
            } else {
                return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
            }
            /*$file_id = $res['file_id'];
            $upload_id = $res['upload_id'];
            $result = curl('PUT', $url, $content, [], 1);
            if ($result['stat']==200) { // 块1传好
                $etag = $result['returnhead']['ETag'];
                $result = $this->fileComplete($file_id, $upload_id, $etag);
                if ($result['stat']!=200) return output($result['body'], $result['stat']);
                else return output('success', 0);
            }*/
        }
        //error_log1('2,url:' . $url .' res:' . json_encode($result));
        return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
    }
    public function Edit($file, $content) {
        $tmp = splitlast($file['path'], '/');
        $folderpath = $tmp[0];
        $filename = $tmp[1];
        $existfile = $this->list_path($file['path']);
        if (isset($existfile['type'])) { // 删掉原文件
            $this->Delete(['id'=>$existfile['file_id']]);
        }
        $tmp1 = '/tmp/' . $filename;
        file_put_contents($tmp1, $content);

        $result = $this->tmpfileCreate($this->list_path($folderpath)['file_id'], $tmp1, $filename);

        if ($result['stat']==201) {
            //error_log1('1,url:' . $url .' res:' . json_encode($result));
            $res = json_decode($result['body'], true);
            $url = $res['part_info_list'][0]['upload_url'];
            if (!$url) { // 无url，应该算秒传
                return output('no up url', 0);
            }
            $file_id = $res['file_id'];
            $upload_id = $res['upload_id'];
            $result = curl('PUT', $url, $content, [], 1);
            if ($result['stat']==200) { // 块1传好
                $tmp2['part_number'] = 1;
                $tmp2['etag'] = $result['returnhead']['ETag'];
                $result = $this->fileComplete($file_id, $upload_id, [ $tmp2 ]);
                if ($result['stat']!=200) return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
                else return output('success', 0);
            }
        }
        //error_log1('2,url:' . $url .' res:' . json_encode($result));
        return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
    }
    public function Create($folder, $type, $name, $content = '') {
        if (!$folder['id']) {
            $res = $this->list_path($folder['path']);
            //error_log1('res:' . json_encode($res));
            $folder['id'] = $res['file_id'];
        }
        if ($type=='folder') {
            $result = $this->folderCreate($folder['id'], $name);
            //error_log1('res:' . json_encode($result));
            return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
        }
        if ($type=='file') {
            $tmp = '/tmp/' . $name;
            file_put_contents($tmp, $content);

            $result = $this->tmpfileCreate($folder['id'], $tmp, $name);

            if ($result['stat']==201) {
                //error_log1('1,url:' . $url .' res:' . json_encode($result));
                $res = json_decode($result['body'], true);
                if (isset($res['exist'])&&$res['exist']!=false) {
                    // 已经有
                    //error_log1('exist:' . json_encode($res));
                    return output('{"type":"file","name":"' . $name . '", "exist":true}', 200);
                }
                if (isset($res['rapid_upload'])&&$res['rapid_upload']!=false) {
                    // 秒传
                    //error_log1('rapid up:' . json_encode($res));
                    return output('{"type":"file","name":"' . $name . '", "rapid_upload":true}', 200);
                }
                $url = $res['part_info_list'][0]['upload_url'];
                $file_id = $res['file_id'];
                $upload_id = $res['upload_id'];
                $result = curl('PUT', $url, $content, [], 1);
                //error_log1('2,url:' . $url .' res:' . json_encode($result));
                if ($result['stat']==200) { // 块1传好
                    $tmp1['part_number'] = 1;
                    $tmp1['etag'] = $result['returnhead']['ETag'];
                    $result = $this->fileComplete($file_id, $upload_id, [ $tmp1 ]);
                    //error_log1('3,url:' . $url .' res:' . json_encode($result));
                    return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
                }
            }
            //error_log1('4,url:' . $url .' res:' . json_encode($result));
            return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
        }
        return output('Type not folder or file.', 500);
    }

    protected function folderCreate($parentId, $folderName) {
        if (strrpos($folderName, '/')) {
            $tmp = splitlast($folderName, '/');
            $parentId = json_decode($this->folderCreate($parentId, $tmp[0])['body'], true)['file_id'];
            $folderName = $tmp[1];
        }
        $url = $this->api_url . '/file/create';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['check_name_mode'] = 'refuse'; // ignore, auto_rename, refuse.
        $data['drive_id'] = $this->default_drive_id;
        $data['name'] = $folderName;
        $data['parent_file_id'] = $parentId;
        $data['type'] = 'folder';

        return curl('POST', $url, json_encode($data), $header);
    }
    protected function fileCreate($parentId, $fileName, $sha1, $size, $part_number) {
        $url = $this->api_url . '/file/create';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;
    
        $data['check_name_mode'] = 'refuse'; // ignore, auto_rename, refuse.
        $data['content_hash'] = $sha1;
        $data['content_hash_name'] = 'sha1';
        $data['content_type'] = '';
        $data['drive_id'] = $this->default_drive_id;
        $data['ignoreError'] = false;
        $data['name'] = $fileName;
        $data['parent_file_id'] = $parentId;
        for ($i=0;$i<$part_number;$i++) {
            $data['part_info_list'][$i]['part_number'] = $i+1;
        }
        $data['size'] = (int)$size;
        $data['type'] = 'file';

        return curl('POST', $url, json_encode($data), $header);
    }
    protected function tmpfileCreate($parentId, $tmpFilePath, $tofileName = '') {
        $sha1 = sha1_file($tmpFilePath);
        if ($tofileName == '') $tofileName = splitlast($tmpFilePath, '/')[1];
        $url = $this->api_url . '/file/create';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['check_name_mode'] = 'refuse'; // ignore, auto_rename, refuse.
        $data['content_hash'] = $sha1;
        $data['content_hash_name'] = 'sha1';
        $data['content_type'] = 'text/plain'; // now only txt
        $data['drive_id'] = $this->default_drive_id;
        $data['ignoreError'] = false;
        $data['name'] = $tofileName;
        $data['parent_file_id'] = $parentId;
        $data['part_info_list'][0]['part_number'] = 1; // now only txt
        $data['size'] = filesize($tmpFilePath);
        $data['type'] = 'file';

        return curl('POST', $url, json_encode($data), $header);
    }
    protected function fileComplete($file_id, $upload_id, $etags) {
        $url = $this->api_url . '/file/complete';

        $header["content-type"] = "application/json; charset=utf-8";
        $header['authorization'] = 'Bearer ' . $this->access_token;

        $data['drive_id'] = $this->default_drive_id;
        $data['file_id'] = $file_id;
        $data['ignoreError'] = false;
        $i = 0;
        foreach ($etags as $etag) {
            $data['part_info_list'][$i]['part_number'] = $i + 1;
            $data['part_info_list'][$i]['etag'] = $etag;
            $i++;
        }
        $data['upload_id'] = $upload_id;

        return curl('POST', $url, json_encode($data), $header);
    }

    public function get_thumbnails_url($path = '/')
    {
        $res = $this->list_path($path);
        $thumb_url = $res['thumbnail'];
        return $thumb_url;
    }
    public function bigfileupload($path)
    {
        if (isset($_POST['uploadid'])) {
            // Complete
            $result = $this->fileComplete($_POST['fileid'], $_POST['uploadid'], json_decode($_POST['etag'], true));
            return output(json_encode($this->files_format(json_decode($result['body'], true))), $result['stat']);
        } else {
            if ($_POST['upbigfilename']=='') return output('error: no file name', 400);
            if (!is_numeric($_POST['filesize'])) return output('error: no file size', 400);
            if (!isset($_POST['filesha1'])) return output('error: no file sha1', 400);

            $tmp = splitlast($_POST['upbigfilename'], '/');
            if ($tmp[1]!='') {
                $fileinfo['name'] = $tmp[1];
                if ($_SERVER['admin']) $fileinfo['path'] = $tmp[0];
            } else {
                $fileinfo['name'] = $_POST['upbigfilename'];
            }
            $fileinfo['size'] = $_POST['filesize'];
            $fileinfo['filelastModified'] = $_POST['filelastModified'];
            if ($_SERVER['admin']) {
                $filename = $fileinfo['name'];
            } else {
                $tmp1 = splitlast($fileinfo['name'], '.');
                if ($tmp1[0]==''||$tmp1[1]=='') $filename = $_POST['filesha1'];
                else $filename = $_POST['filesha1'] . '.' . $tmp1[1];
            }
            /*if ($fileinfo['size']>10*1024*1024) {
                $cachefilename = spurlencode( $fileinfo['path'] . '/.' . $fileinfo['filelastModified'] . '_' . $fileinfo['size'] . '_' . $fileinfo['name'] . '.tmp', '/');
                $getoldupinfo=$this->list_path(path_format($path . '/' . $cachefilename));
                //echo json_encode($getoldupinfo, JSON_PRETTY_PRINT);
                if ($getoldupinfo['type']=='file'&&$getoldupinfo['size']<5120) {
                    $getoldupinfo_j = curl('GET', $getoldupinfo['url']);
                    $getoldupinfo = json_decode($getoldupinfo_j['body'], true);
                    //if ( json_decode( curl('GET', $getoldupinfo['uploadUrl'])['body'], true)['@odata.context']!='' ) return output($getoldupinfo_j['body'], $getoldupinfo_j['stat']);
                }
            }*/
            $parent = $this->list_path($path . '/' . $fileinfo['path']);
            if (isset($parent['file_id'])) {
                $parent_file_id = $parent['file_id'];
            } else {
                $res = $this->folderCreate($this->list_path($path)['file_id'], $fileinfo['path']);
                //error_log1($res['body']);
                $parent_file_id = json_decode($res['body'], true)['file_id'];
            }
            $response = $this->fileCreate($parent_file_id, $filename, $_POST['filesha1'], $fileinfo['size'], ceil($fileinfo['size']/$_POST['chunksize']));
            $res = json_decode($response['body'], true);
            if (isset($res['exist'])) {
                // 已经有
                //error_log1('exist:' . json_encode($res));
                return output(json_encode($this->files_format(json_decode($response['body'], true))), $response['stat']);
                //return output('{"type":"file","name":"' . $_POST['upbigfilename'] . '", "exist":true}', 200);
            }
            if (isset($res['rapid_upload'])&&$res['rapid_upload']!=false) {
                // 秒传
                //error_log1('rapid up:' . json_encode($res));
                return output(json_encode($this->files_format(json_decode($response['body'], true))), $response['stat']);
                //return output('{"type":"file","name":"' . $_POST['upbigfilename'] . '", "rapid upload":true}', 200);
            }
            //if ($response['stat']<500) {
            //    $responsearry = json_decode($response['body'], true);
            //    if (isset($responsearry['error'])) return output($response['body'], $response['stat']);
            //    $fileinfo['uploadUrl'] = $responsearry['uploadUrl'];
            //    if ($fileinfo['size']>10*1024*1024) $this->MSAPI('PUT', path_format($path . '/' . $cachefilename), json_encode($fileinfo, JSON_PRETTY_PRINT), $this->access_token);
            //}
            return output($response['body'], $response['stat']);
        }
    }

    public function AddDisk() {
        global $constStr;
        global $EnvConfigs;

        $envs = '';
        foreach ($EnvConfigs as $env => $v) if (isCommonEnv($env)) $envs .= '\'' . $env . '\', ';
        $url = path_format($_SERVER['PHP_SELF'] . '/');

        if (isset($_GET['install0']) && $_POST['disktag_add']!='') {
            $_POST['disktag_add'] = preg_replace('/[^0-9a-zA-Z|_]/i', '', $_POST['disktag_add']);
            $f = substr($_POST['disktag_add'], 0, 1);
            if (strlen($_POST['disktag_add'])==1) $_POST['disktag_add'] .= '_';
            if (isCommonEnv($_POST['disktag_add'])) {
                return message('Do not input ' . $envs . '<br><button onclick="location.href = location.href;">'.getconstStr('Refresh').'</button>
                <script>
                var expd = new Date();
                expd.setTime(expd.getTime()+1);
                var expires = "expires="+expd.toGMTString();
                document.cookie=\'disktag=; path=/; \'+expires;
                </script>', 'Error', 201);
            } elseif (!(('a'<=$f && $f<='z') || ('A'<=$f && $f<='Z'))) {
                return message('Please start with letters<br><button onclick="location.href = location.href;">'.getconstStr('Refresh').'</button>
                <script>
                var expd = new Date();
                expd.setTime(expd.getTime()+1);
                var expires = "expires="+expd.toGMTString();
                document.cookie=\'disktag=; path=/; \'+expires;
                </script>', 'Error', 201);
            }
            $res = curl('POST', $this->auth_url, json_encode([ 'refresh_token' => $_POST['refresh_token'] ]), ["content-type"=>"application/json; charset=utf-8"]);
            //return output($res['body']);
            if ($res['stat']!=200) {
                return message($res['body'], $res['stat'], $res['stat']);
            }
            //var_dump($res['body']);
            $result = json_decode($res['body'], true);

            $tmp = null;
            foreach ($EnvConfigs as $env => $v) if (isInnerEnv($env)) $tmp[$env] = '';

            $tmp['refresh_token'] = $result['refresh_token'];
            $tmp['default_drive_id'] = $result['default_drive_id'];
            $tmp['default_sbox_drive_id'] = $result['default_sbox_drive_id'];
            $tmp['token_expires'] = time()+3*24*60*60;
            $tmp['Driver'] = 'Aliyundrive';
            $tmp['disktag_add'] = $_POST['disktag_add'];
            $tmp['diskname'] = $_POST['diskname'];
            //error_log(json_encode($tmp));

            $response = setConfigResponse( setConfig($tmp, $this->disktag) );
            if (api_error($response)) {
                $html = api_error_msg($response);
                $title = 'Error';
                return message($html, $title, 201);
            } else {
                savecache('access_token', $result['access_token'], $this->disktag, $result['expires_in'] - 60);
                $str .= '<meta http-equiv="refresh" content="5;URL=' . $url . '">
                <script>
                var expd = new Date();
                expd.setTime(expd.getTime()+1);
                var expires = "expires="+expd.toGMTString();
                document.cookie=\'disktag=; path=/; \'+expires;
                </script>';
                return message($str, getconstStr('WaitJumpIndex'), 201);
            }

            /*$api = $this->api_url . '/user/get';
            $header['authorization'] = 'Bearer ' . $this->access_token;
            return json_encode(curl('GET', $api, '', $header));*/
        }

        $html = '
<div>
    <form id="form1" action="" method="post" onsubmit="return notnull(this);">
        ' . getconstStr('DiskTag') . ': (' . getConfig('disktag') . ')
        <input type="text" name="disktag_add" placeholder="' . getconstStr('EnvironmentsDescription')['disktag'] . '" style="width:100%"><br>
        ' . getconstStr('DiskName') . ':
        <input type="text" name="diskname" placeholder="' . getconstStr('EnvironmentsDescription')['diskname'] . '" style="width:100%"><br>
        <br>
        <div>填入refresh_token:
            <input type="text" name="refresh_token" placeholder="' . getconstStr(' ') . '" style="width:100%"><br>
        </div>
        <br>

        <input type="submit" value="' . getconstStr('Submit') . '">
    </form>
</div>
    <script>
        function notnull(t)
        {
            if (t.disktag_add.value==\'\') {
                alert(\'' . getconstStr('DiskTag') . '\');
                return false;
            }
            envs = [' . $envs . '];
            if (envs.indexOf(t.disktag_add.value)>-1) {
                alert("Do not input ' . $envs . '");
                return false;
            }
            var reg = /^[a-zA-Z]([_a-zA-Z0-9]{1,20})$/;
            if (!reg.test(t.disktag_add.value)) {
                alert(\'' . getconstStr('TagFormatAlert') . '\');
                return false;
            }
            if (t.refresh_token.value==\'\') {
                    alert(\'Input refresh_token\');
                    return false;
            }
            
            document.getElementById("form1").action="?install0&disktag=" + t.disktag_add.value + "&AddDisk=Aliyundrive";
            //var expd = new Date();
            //expd.setTime(expd.getTime()+(2*60*60*1000));
            //var expires = "expires="+expd.toGMTString();
            //document.cookie=\'disktag=\'+t.disktag_add.value+\'; path=/; \'+expires;
            return true;
        }
    </script>';
        $title = 'Select Account Type';
        return message($html, $title, 201);
    }
    protected function get_access_token($refresh_token) {
        if (!($this->access_token = getcache('access_token', $this->disktag))) {
            $p=0;
            $tmp1['refresh_token'] = $refresh_token;
            while ($response['stat']==0&&$p<3) {
                $response = curl('POST', $this->auth_url, json_encode($tmp1), ["content-type"=>"application/json; charset=utf-8"]);
                $p++;
            }
            error_log1(json_encode($response));
            if ($response['stat']==200) $ret = json_decode($response['body'], true);
            if (!isset($ret['access_token'])) {
                error_log1('failed to get [' . $this->disktag . '] access_token. response: ' . $response['stat'] . $response['body']);
                //$response['body'] = json_encode(json_decode($response['body']), JSON_PRETTY_PRINT);
                $response['body'] .= 'failed to get [' . $this->disktag . '] access_token.';
                $this->error = $response;
                return false;
            }
            $tmp = $ret;
            $tmp['access_token'] = '******';
            $tmp['refresh_token'] = '******';
            error_log1('[' . $this->disktag . '] Get access token:' . json_encode($tmp, JSON_PRETTY_PRINT));
            $this->access_token = $ret['access_token'];
            savecache('access_token', $this->access_token, $this->disktag, $ret['expires_in'] - 300);
            if (time()>getConfig('token_expires', $this->disktag)) setConfig([ 'refresh_token' => $ret['refresh_token'], 'token_expires' => time()+3*24*60*60 ], $this->disktag);
            return true;
        }
        return true;
    }
}
