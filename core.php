<?php
use OSS\Core\OssException;
use OSS\OssClient;

/**
 * log
 * @param $data mixed
 * @param $title string
 */
function iaso_log($data, string $title = '')
{
    $payme_options = get_option('iaso_options');
    $debug = $payme_options['iaso_field_open_debug'] ?? 0;
    if ($debug) {
        $log_file = __DIR__ . '/iaso.log';
        if (is_array($data)) {
            $content = json_encode($data, 320);
        } elseif (is_object($data)) {
            $content = json_encode(iaso_obj_to_array($data), 320);
        } elseif (!is_string($data) && !is_numeric($data)) {
            $content = serialize($data);
        } else {
            $content = $data;
        }
        $date_time = $title ? date('Y-m-d H:i:s') . " | {$title} | " : date('Y-m-d H:i:s') . " | " ;
        file_put_contents($log_file, "{$date_time}{$content}" . PHP_EOL, FILE_APPEND);
    }
}

function iaso_obj_to_array($obj)
{
    $vars = get_object_vars ( $obj );
    $array = array ();
    foreach ( $vars as $key => $value ) {
        $array [$key] = $value;
    }
    return $array;
}

function iaso_clear_log()
{
    $log_file = __DIR__ . '/iaso.log';
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
    }
}

// check option values
function iaso_notice()
{
    $screen = get_current_screen();
    $value = get_option('iaso_options');
    if ($screen->id == 'settings_page_image_auto_sync_oss' && isset($value['iaso_field_open']) && $value['iaso_field_open']) {
        if (
            empty($value['iaso_field_oss_key']) ||
            empty($value['iaso_field_oss_secret']) ||
            empty($value['iaso_field_oss_bucket']) ||
            empty($value['iaso_field_oss_endpoint']) ||
            empty($value['iaso_field_oss_domain'])
        ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php esc_html_e('Some important values shouldn\'t be empty', 'image-auto-sync-oss'); ?>
                </p>
            </div>
            <?php
        } else {
            try {
                $ossClient = new OssClient($value['iaso_field_oss_key'], $value['iaso_field_oss_secret'], $value['iaso_field_oss_endpoint']);
                // $res = $ossClient->doesBucketExist($value['iaso_field_oss_bucket']);
                // $res = $ossClient->getBucketInfo($value['iaso_field_oss_bucket']);
                $res = $ossClient->listObjects($value['iaso_field_oss_bucket']);
            } catch (OssException $e) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <?php  esc_html_e(_e('oss options error: ', 'image-auto-sync-oss') . $e->getMessage()); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
}
add_action('admin_notices', 'iaso_notice');

// clean debug file after option updated
function iaso_after_option_updated($option_name, $old_value, $value)
{
    if ($option_name == 'iaso_options') {
        if (!isset($value['iaso_field_open_debug'])) {
            iaso_clear_log();
        }
        if ($old_value['iaso_field_oss_subPath'] != $value['iaso_field_oss_subPath'] || $old_value['iaso_field_oss_domain'] != $value['iaso_field_oss_domain']) {
            iaso_set_history_map();
        }
    }
}
add_action('updated_option', 'iaso_after_option_updated', 10, 3);


function iaso_post_updated($post_ID, $post_after, $post_before)
{
    // 跳过自动保存
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
        iaso_log("skip auto_save");
        return;
    }

    iaso_log("编辑文章 {$post_ID}");
    $options = get_option('iaso_options');
    if (isset($options['iaso_field_open']) && $options['iaso_field_open']) {
        iaso_handler($post_ID, $options);
    }
}

add_action('post_updated', 'iaso_post_updated', 10, 3);

function iaso_handler($post_id, $options)
{
    $tempPath = IASO_PATH . 'temp/';
    $maps = iaso_get_history_map();
    $updateMap = false;
    $post = get_post($post_id);
    preg_match_all('/src.*?(http.*?\.(jpg|jpeg|png))/', $post->post_content, $images);
    if(!empty($images[1])) {
        $newImgs = [];
        $oldImgs = [];
        foreach ($images[1] as $img) {
            iaso_log("匹配图片：" . $img);
            $mapImg = $maps[$img] ?? '';
            $domain = rtrim($options['iaso_field_oss_domain'], '/') . '/';
            // get subPath
            $subPath = $options['iaso_field_oss_subPath'] ?? '';
            $domainPath = $subPath ? $domain . $subPath : $domain;
            // 非 cdn 图片
            if (strpos($img, $domainPath) === false) {
                // 存在匹配记录并且同域名同目录
                if ($mapImg && strpos($mapImg, $domainPath) !== false) {
                    iaso_log("{$img} ====> $mapImg", '使用历史替换');
                    $newImgs[] = $mapImg;
                    $oldImgs[] = $img;
                } else {
                    $parseUrl = parse_url($img);
                    $pathinfo = iaso_mb_pathinfo($parseUrl['path']);
                    $ext = strtolower($pathinfo['extension']);
                    $outFile = $tempPath . uniqid() . '.' . $ext;
                    iaso_log("保存地址:{$outFile}");
                    if(iaso_download_image($img, $outFile) && file_exists($outFile)) {
                        iaso_log("下载成功");
                    } else {
                        iaso_log("下载失败:{$img}");
                    }
                    // 上传
                    $res = iaso_upload_image($outFile, $options);
                    if($res) {
                        $newImg = $domain . $res;
                        iaso_log($newImg);
                        $newImgs[] = $newImg;
                        $oldImgs[] = $img;
                        $maps[$img] = $newImg;
                        $updateMap = true;
                    }
                    @unlink($outFile);
                }

            } else {
                iaso_log('cdn图片跳过');
            }
        }
        if(!empty($newImgs)) {
            $newContent = str_replace($oldImgs, $newImgs, $post->post_content);
            $postArr = array(
                // 'ID' => $post_id,
                'post_content' => $newContent,
            );
            if ($post->post_content_filtered) {
                $postArr['post_content_filtered'] = str_replace($oldImgs, $newImgs, $post->post_content_filtered);
            }
            // 避免更新又触发当前处理流程
            remove_action('post_updated', 'iaso_post_updated', 10);
            // $res = wp_update_post($postArr, true);
            global $wpdb;
            if (false === $wpdb->update( $wpdb->posts, $postArr, array( 'ID' => $post_id ))) {
                iaso_log("post_id = {$post_id} : " . $wpdb->last_error, '数据库更新失败');
            } else {
                iaso_log("图片处理完成 {$post->ID}");
            }
        }
        if (isset($options['iaso_field_del_local_image']) && $options['iaso_field_del_local_image']) {
            iaso_del_local_image($post_id, $oldImgs);
        }
        if ($updateMap) {
            iaso_set_history_map($maps);
        }
    }
}

/**
 * pathinfo
 * @param $filePath
 * @return array
 * @internal param $filePath
 */
function iaso_mb_pathinfo($filePath)
{
    $path_parts = [];
    $path_parts ['dirname'] = rtrim(substr($filePath, 0, strrpos($filePath, '/')), "/");
    $path_parts ['basename'] = ltrim(substr($filePath, strrpos($filePath, '/')), "/");
    $path_parts ['extension'] = substr(strrchr($filePath, '.'), 1);
    $path_parts ['filename'] = ltrim(substr($path_parts ['basename'], 0, strrpos($path_parts ['basename'], '.')), "/");
    return $path_parts;
}

/**
 * download
 * @param $url
 * @param $outFile
 * @return bool
 */
function iaso_download_image($url, $outFile)
{
    $opts = [
        'http' => ['method' => 'GET', 'timeout' => 15,]
    ];
    $context = stream_context_create($opts);
    try {
        $file = file_get_contents($url, false, $context);
        if ($file) {
            file_put_contents($outFile, $file);
        }
    } catch (\Exception $e) {
        iaso_log('download error');
        iaso_log($e->getTraceAsString());
        return false;
    }
    return true;
}


/**
 * image upload
 * @param $filePath
 * @param array $options
 * @return bool
 */

function iaso_upload_image($filePath, $options)
{
    $accessKeyId = $options['iaso_field_oss_key'];
    $accessKeySecret = $options['iaso_field_oss_secret'];
    $bucket = $options['iaso_field_oss_bucket'];
    $endpoint = $options['iaso_field_oss_endpoint'];
    $subPath = $options['iaso_field_oss_subPath'] ?? '';

    if(!is_file($filePath)) {
        iaso_log("file {$filePath} is not valid");
        return false;
    }
    $info = pathinfo($filePath);
    $ym = date('Ym');
    $key = $subPath ? "{$subPath}/{$ym}/{$info['basename']}" : "{$ym}/{$info['basename']}";
    iaso_log("上传文件名:{$key}");

    try {
        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $ossClient->uploadFile($bucket, $key, $filePath);
    } catch (OssException $e) {
        iaso_log("上传文件错误：" . $e->getMessage());
        return false;
    }

    iaso_log('上传成功');
    return $key;
}

function iaso_get_history_map()
{
    $mapFile = IASO_PATH . 'map.json';
    if (!file_exists($mapFile)) {
        iaso_set_history_map();
    }
    return json_decode(file_get_contents($mapFile), true);
}

function iaso_set_history_map($maps=[])
{
    $mapFile = IASO_PATH . 'map.json';
    file_put_contents($mapFile, json_encode($maps));
}

function iaso_del_local_image($post_ID, $oldImgs)
{
    iaso_log("删除本地附件: post_id = " . $post_ID);
    global $wpdb;
    // 当前文章所有附件
    $attachmentIds = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE 
                              post_parent = $post_ID AND 
                              post_type = 'attachment' AND 
                              post_mime_type like 'image%'");

    if ($attachmentIds) {
        // 被其他文章使用的附件
        $excludeAttachmentIds = $wpdb->get_col("SELECT meta_value FROM $wpdb->postmeta WHERE 
                                         meta_key = '_thumbnail_id' AND 
                                         post_id != $post_ID AND
                                         meta_value IN (" . join(',', $attachmentIds) . ")");
        $excludeAttachmentIds = array_values(array_unique($excludeAttachmentIds));
        // 没有被其他文章使用的附件 需要删除
        $delIds = array_values(array_diff($attachmentIds, $excludeAttachmentIds));
        if ($delIds) {
            iaso_log("全部：" . join('|', $attachmentIds) .
                "，排除：" . join('|', $excludeAttachmentIds) .
                "，删除：" . join("|", $delIds), '删除本地附件明细');

            foreach ($delIds as $value_id) {
                wp_delete_attachment( $value_id, true );
            }
        }
    }
    // del _thumbnail_id postmeta
    $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' AND post_id = $post_ID" );
}
